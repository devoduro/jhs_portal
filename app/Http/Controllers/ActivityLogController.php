<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Who did what and when: logins plus every change signed-in users make (payments, billing,
 * scores, settings...), filterable by person, kind of action, date and free text, with a CSV export.
 */
class ActivityLogController extends Controller
{
    /** action => [label, icon, badge classes] */
    public const ACTIONS = [
        'login' => ['Sign in', 'fa-right-to-bracket', 'bg-green-50 text-green-700 ring-green-100'],
        'logout' => ['Sign out', 'fa-right-from-bracket', 'bg-gray-100 text-gray-600 ring-gray-200'],
        'payment' => ['Payment', 'fa-cash-register', 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
        'billing' => ['Billing', 'fa-file-invoice', 'bg-blue-50 text-blue-700 ring-blue-100'],
        'scores' => ['Scores & reports', 'fa-pen-to-square', 'bg-purple-50 text-purple-700 ring-purple-100'],
        'notice' => ['Parent message', 'fa-paper-plane', 'bg-sky-50 text-sky-700 ring-sky-100'],
        'sms' => ['SMS', 'fa-sms', 'bg-sky-50 text-sky-700 ring-sky-100'],
        'settings' => ['Settings', 'fa-gear', 'bg-orange-50 text-orange-700 ring-orange-100'],
        'pupil' => ['Pupils', 'fa-user-graduate', 'bg-indigo-50 text-indigo-700 ring-indigo-100'],
        'class' => ['Classes', 'fa-chalkboard', 'bg-indigo-50 text-indigo-700 ring-indigo-100'],
        'academic' => ['Academics', 'fa-book', 'bg-teal-50 text-teal-700 ring-teal-100'],
        'user' => ['Accounts & roles', 'fa-user-shield', 'bg-red-50 text-red-700 ring-red-100'],
        'other' => ['Other', 'fa-circle-dot', 'bg-gray-100 text-gray-600 ring-gray-200'],
    ];

    public function index(Request $request)
    {
        $query = $this->filtered($request)->with('user');

        $perPage = in_array((int) $request->input('per_page'), [20, 50, 100, 200], true) ? (int) $request->input('per_page') : 20;
        $logs = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();

        $today = today();
        $stats = [
            'total' => ActivityLog::count(),
            'today' => ActivityLog::where('created_at', '>=', $today)->count(),
            'users_today' => ActivityLog::where('created_at', '>=', $today)->whereNotNull('user_id')->distinct()->count('user_id'),
            'changes_today' => ActivityLog::where('created_at', '>=', $today)->whereNotIn('action', ['login', 'logout'])->count(),
            'logins_week' => ActivityLog::where('action', 'login')->where('created_at', '>=', $today->copy()->subDays(6))->count(),
        ];

        // Events per day for the last 7 days.
        $perDay = ActivityLog::where('created_at', '>=', $today->copy()->subDays(6))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as n')->groupBy('day')->pluck('n', 'day');
        $week = collect(range(6, 0))->map(fn ($i) => [
            'label' => $today->copy()->subDays($i)->format('D'),
            'count' => (int) ($perDay[$today->copy()->subDays($i)->toDateString()] ?? 0),
        ]);

        // People who appear in the log, for the "who" filter.
        $people = User::whereIn('id', ActivityLog::whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name']);

        $actions = self::ACTIONS;
        $presentActions = ActivityLog::distinct()->pluck('action')->all();

        return view('admin.activity-logs.index', compact('logs', 'stats', 'week', 'people', 'actions', 'presentActions'));
    }

    public function export(Request $request)
    {
        $logs = $this->filtered($request)->with('user')->orderByDesc('created_at')->orderByDesc('id')->limit(20000)->get();

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel reads UTF-8
            fputcsv($out, ['Time', 'User', 'Action', 'Description', 'IP address', 'Browser']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user->name ?? 'System',
                    self::ACTIONS[$log->action][0] ?? $log->action,
                    $log->description,
                    $log->ip_address,
                    self::device($log->user_agent),
                ]);
            }
            fclose($out);
        }, 'activity-log-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): Builder
    {
        return ActivityLog::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)->orWhere('subject', 'like', $term)->orWhere('ip_address', 'like', $term)->orWhere('action', 'like', $term));
            })
            ->when($request->filled('action'), function ($q) use ($request) {
                // "Changes" = everything except signing in and out.
                $request->action === 'changes' ? $q->whereNotIn('action', ['login', 'logout']) : $q->where('action', $request->action);
            })
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->user_id))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()));
    }

    /** "Chrome on Windows" from a raw user-agent string. */
    public static function device(?string $agent): string
    {
        if (!$agent) {
            return '—';
        }

        $browser = match (true) {
            Str::contains($agent, ['Edg/', 'EdgA/']) => 'Edge',
            Str::contains($agent, ['OPR/', 'Opera']) => 'Opera',
            Str::contains($agent, 'Firefox') => 'Firefox',
            Str::contains($agent, 'Chrome') => 'Chrome',
            Str::contains($agent, 'Safari') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            Str::contains($agent, 'Windows') => 'Windows',
            Str::contains($agent, 'Android') => 'Android',
            Str::contains($agent, ['iPhone', 'iPad']) => 'iOS',
            Str::contains($agent, 'Mac OS') => 'Mac',
            Str::contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $browser . ($os ? " on {$os}" : '');
    }
}
