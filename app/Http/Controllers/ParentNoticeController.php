<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\EmailLog;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Services\BulkSmsService;
use App\Services\FeeLedgerService;
use App\Services\ParentNotifier;
use Illuminate\Http\Request;

/**
 * Send pupils' results and fee statements to parents by SMS, email or both - for chosen
 * classes, the whole school, or one pupil (the buttons on a pupil's fee account use the same
 * endpoint).
 */
class ParentNoticeController extends Controller
{
    public function index(Request $request)
    {
        $terms = $this->terms();
        $term = $request->filled('semester_id')
            ? $terms->firstWhere('id', (int) $request->semester_id)
            : ($terms->firstWhere('is_current', true) ?? $terms->first());

        $classGroups = ClassGroup::orderBy('level')->orderBy('name')->get();
        $classIds = array_filter((array) $request->input('class_group_ids', []));

        $pupils = Student::where('status', 'active')
            ->when($classIds, fn ($q) => $q->whereIn('class_group_id', $classIds))
            ->get(['id', 'emergency_contact_phone', 'phone', 'parent_email', 'class_group_id']);

        $withResults = $term ? Result::where('semester_id', $term->id)->whereIn('student_id', $pupils->pluck('id'))->distinct()->pluck('student_id')->all() : [];
        $owing = 0;
        if ($term) {
            $owing = collect(FeeLedgerService::positions($pupils->pluck('id'), $term))->filter(fn ($p) => $p['balance'] > 0.009)->count();
        }

        $counts = [
            'pupils' => $pupils->count(),
            'phones' => $pupils->filter(fn ($s) => BulkSmsService::parentPhone($s))->count(),
            'emails' => $pupils->filter(fn ($s) => ParentNotifier::parentEmail($s))->count(),
            'with_results' => count($withResults),
            'owing' => $owing,
        ];

        $emailLogs = EmailLog::with('student')->latest('id')->limit(15)->get();

        return view('parent-notices.index', compact('terms', 'term', 'classGroups', 'classIds', 'counts', 'emailLogs'));
    }

    public function send(Request $request, ParentNotifier $notifier)
    {
        $data = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'kinds' => 'required|array|min:1',
            'kinds.*' => 'in:results,fees',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,email',
            'class_group_ids' => 'nullable|array',
            'class_group_ids.*' => 'exists:class_groups,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'whole_school' => 'nullable|boolean',
            'only_owing' => 'nullable|boolean',
        ], [
            'kinds.required' => 'Choose what to send: results, fees, or both.',
            'channels.required' => 'Choose how to send it: SMS, email, or both.',
        ]);

        $classIds = array_filter((array) ($data['class_group_ids'] ?? []));
        $studentIds = array_filter((array) ($data['student_ids'] ?? []));

        if (!$classIds && !$studentIds && !$request->boolean('whole_school')) {
            return back()->withInput()->with('error', 'Choose at least one class, or tick "whole school".');
        }

        $term = Semester::with('academicYear')->findOrFail($data['semester_id']);

        $pupils = Student::with('classGroup')->where('status', 'active')
            ->when($studentIds, fn ($q) => $q->whereIn('id', $studentIds))
            ->when(!$studentIds && $classIds, fn ($q) => $q->whereIn('class_group_id', $classIds))
            ->orderBy('level')->orderBy('full_name')
            ->get();

        if ($pupils->isEmpty()) {
            return back()->withInput()->with('error', 'No active pupils match that selection.');
        }

        // "Only those who owe" narrows the fees message; results always go to everyone with scores.
        $owingIds = null;
        if ($request->boolean('only_owing') && in_array('fees', $data['kinds'], true)) {
            $positions = FeeLedgerService::positions($pupils->pluck('id'), $term);
            $owingIds = collect($positions)->filter(fn ($p) => $p['balance'] > 0.009)->keys()->all();
        }

        @set_time_limit(0);

        $tally = [];   // "results.sms" => [sent, failed, no_contact, nothing]
        foreach ($pupils as $pupil) {
            foreach ($data['kinds'] as $kind) {
                if ($kind === 'fees' && $owingIds !== null && !in_array($pupil->id, $owingIds, true)) {
                    continue;
                }

                foreach ($notifier->send($pupil, $term, $kind, $data['channels']) as $channel => $outcome) {
                    $tally["{$kind}.{$channel}"][$outcome] = ($tally["{$kind}.{$channel}"][$outcome] ?? 0) + 1;
                }
            }
        }

        return back()->with($this->anySent($tally) ? 'success' : 'error', $this->summary($tally, $term));
    }

    private function anySent(array $tally): bool
    {
        return collect($tally)->contains(fn ($row) => ($row['sent'] ?? 0) > 0);
    }

    private function summary(array $tally, Semester $term): string
    {
        $label = ['results.sms' => 'Results by SMS', 'results.email' => 'Results by email', 'fees.sms' => 'Fees by SMS', 'fees.email' => 'Fees by email'];
        $parts = [];

        foreach ($label as $key => $name) {
            if (!isset($tally[$key])) {
                continue;
            }
            $row = $tally[$key];
            $text = "{$name}: " . ($row['sent'] ?? 0) . ' sent';
            $extras = array_filter([
                ($row['failed'] ?? 0) ? ($row['failed'] . ' failed') : null,
                ($row['no_contact'] ?? 0) ? ($row['no_contact'] . ' with no ' . (str_ends_with($key, 'sms') ? 'phone' : 'email') . ' on file') : null,
                ($row['nothing'] ?? 0) ? ($row['nothing'] . ' with nothing to report') : null,
            ]);
            $parts[] = $text . ($extras ? ' (' . implode(', ', $extras) . ')' : '');
        }

        return "{$term->full_name} - " . ($parts ? implode('. ', $parts) . '.' : 'nothing was sent.');
    }

    private function terms()
    {
        return Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
    }
}
