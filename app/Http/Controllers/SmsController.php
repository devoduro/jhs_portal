<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\SmsCampaign;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\Student;
use App\Services\BulkSmsService;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SmsController extends Controller
{
    private const FILTERS = ['audience', 'section', 'class_group_ids', 'level', 'gender', 'fees', 'min_balance', 'search', 'staff_role', 'student_ids'];

    /**
     * Compose a message to parents (or staff), choosing who gets it with filters.
     */
    public function index(Request $request, BulkSmsService $bulk)
    {
        $filters = $this->filters($request);
        $templates = SmsTemplate::orderBy('name')->get();
        $classGroups = ClassGroup::orderBy('level')->orderBy('name')->get();
        $roles = \Spatie\Permission\Models\Role::orderBy('name')->pluck('name');

        if ($filters['audience'] === 'staff') {
            $people = $bulk->staffQuery($filters)->paginate(25)->withQueryString();
            $all = $bulk->staffQuery($filters)->get();
            $withPhone = $all->filter(fn ($u) => $u->resetPhone())->count();
            $counts = ['matched' => $all->count(), 'with_phone' => $withPhone, 'no_phone' => $all->count() - $withPhone];
            $sample = null;
        } else {
            $people = $bulk->pupilQuery($filters)->paginate(25)->withQueryString();
            $all = $bulk->pupilQuery($filters)->get();
            $phones = $all->map(fn ($s) => BulkSmsService::parentPhone($s))->filter();
            $counts = [
                'matched' => $all->count(),
                'with_phone' => $phones->count(),
                'no_phone' => $all->count() - $phones->count(),
                'families' => $phones->map(fn ($p) => substr(preg_replace('/\D/', '', $p), -9))->unique()->count(),
            ];
            $sample = $all->first(fn ($s) => BulkSmsService::parentPhone($s));
        }

        $sampleValues = $sample ? [
            '{parent}' => $sample->emergency_contact_name ?: 'Parent',
            '{pupil}' => $sample->full_name,
            '{first_name}' => preg_split('/\s+/', trim($sample->full_name))[1] ?? $sample->full_name,
            '{class}' => $sample->classGroup->name ?? \App\Support\FormLabel::label($sample->level),
            '{balance}' => 'GHS ' . number_format(max(0, (float) $sample->balance), 2),
            '{admission_no}' => $sample->index_number,
        ] : [];
        $sampleValues['{school}'] = \App\Models\Setting::get('institution_name', 'the school');
        $sampleValues['{name}'] = $filters['audience'] === 'staff' ? (auth()->user()->name) : ($sampleValues['{parent}'] ?? 'Parent');

        return view('sms.index', compact('filters', 'templates', 'classGroups', 'roles', 'people', 'counts', 'sampleValues'));
    }

    /**
     * Send the composed message to everyone the filters match.
     */
    public function send(Request $request, PastechSmsService $sms, BulkSmsService $bulk)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:612'],
            'audience' => ['nullable', 'in:parents,staff'],
            'section' => ['nullable', 'in:preschool,primary,jhs'],
            'class_group_ids' => ['nullable', 'array'],
            'class_group_ids.*' => ['exists:class_groups,id'],
            'level' => ['nullable', 'integer', \App\Support\FormLabel::rule()],
            'gender' => ['nullable', 'in:Male,Female'],
            'fees' => ['nullable', 'in:owing,paid'],
            'min_balance' => ['nullable', 'numeric', 'min:0'],
            'search' => ['nullable', 'string', 'max:100'],
            'staff_role' => ['nullable', 'string', 'exists:roles,name'],
        ], ['message.required' => 'Type the message to send.']);

        $filters = $this->filters($request);
        $messages = $bulk->buildMessages($filters, $request->message);

        if ($messages->isEmpty()) {
            return back()->withInput()->with('error', 'Nobody with a phone number matches the selected filters, so nothing was sent.');
        }

        @set_time_limit(0);

        $campaign = SmsCampaign::create([
            'message' => $request->message,
            'filters' => array_filter($filters),
            'total_recipients' => $messages->count(),
            'sent_by' => Auth::id(),
        ]);

        $successCount = 0;
        $failedCount = 0;

        foreach ($messages as $item) {
            try {
                $result = $sms->sendSms($item['phone'], $item['message']);
            } catch (\Throwable $e) {
                report($e);
                $result = ['success' => false, 'code' => null, 'message' => 'Could not reach the SMS gateway'];
            }

            SmsLog::create([
                'sms_campaign_id' => $campaign->id,
                'type' => SmsLog::TYPE_CAMPAIGN,
                'student_id' => $item['student_id'],
                'phone' => $item['phone'],
                'message' => $item['message'],
                'success' => (bool) ($result['success'] ?? false),
                'response_code' => $result['code'] ?? null,
                'response_message' => $result['message'] ?? null,
            ]);

            ($result['success'] ?? false) ? $successCount++ : $failedCount++;
        }

        $campaign->update(['success_count' => $successCount, 'failed_count' => $failedCount]);

        return redirect()->route('sms.history.show', $campaign)
            ->with($failedCount && !$successCount ? 'error' : 'success', "Message sent to {$successCount} of {$messages->count()} recipient(s)" . ($failedCount ? " — {$failedCount} failed (see the list below)." : '.'));
    }

    private function filters(Request $request): array
    {
        $filters = $request->only(self::FILTERS);
        $filters['audience'] = ($filters['audience'] ?? 'parents') === 'staff' ? 'staff' : 'parents';
        $filters['class_group_ids'] = array_filter((array) ($filters['class_group_ids'] ?? []));

        return $filters;
    }

    /**
     * Show sent message history (campaigns).
     */
    public function history()
    {
        $campaigns = SmsCampaign::with('sender')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('sms.history', compact('campaigns'));
    }

    /**
     * Show detailed delivery logs for a single campaign.
     */
    public function showCampaign(SmsCampaign $campaign)
    {
        $logs = $campaign->logs()->with('student')->orderByDesc('created_at')->paginate(50);

        return view('sms.campaign-detail', compact('campaign', 'logs'));
    }

    /**
     * Automatic SMS to parents - terminal report links, fee receipts and balance reminders -
     * as distinct from messages composed on the Send SMS page (see history()).
     */
    public function parentNotifications(Request $request)
    {
        $classGroups = ClassGroup::orderBy('level')->orderBy('name')->get();

        $query = SmsLog::whereIn('type', [SmsLog::TYPE_GRADES, SmsLog::TYPE_FEES])
            ->with('student.classGroup')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('class_group_id')) {
            $query->whereHas('student', fn ($q) => $q->where('class_group_id', $request->class_group_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('index_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('success', $request->status === 'sent');
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(20)->withQueryString();

        return view('sms.parent-notifications', compact('logs', 'classGroups'));
    }

    // ──────────────────────────────────────────────
    // SMS Templates
    // ──────────────────────────────────────────────

    public function templates()
    {
        $templates = SmsTemplate::with('creator')->orderByDesc('created_at')->get();

        return view('sms.templates.index', compact('templates'));
    }

    public function storeTemplate(Request $request)
    {
        $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:612'],
        ]);

        SmsTemplate::create([
            'name'       => $request->name,
            'message'    => $request->message,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('sms.templates.index')->with('success', 'SMS template created successfully.');
    }

    public function updateTemplate(Request $request, SmsTemplate $template)
    {
        $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:612'],
        ]);

        $template->update($request->only(['name', 'message']));

        return redirect()->route('sms.templates.index')->with('success', 'SMS template updated successfully.');
    }

    public function destroyTemplate(SmsTemplate $template)
    {
        $template->delete();

        return redirect()->route('sms.templates.index')->with('success', 'SMS template deleted successfully.');
    }
}
