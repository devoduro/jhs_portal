<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes an activity-log entry for every change a signed-in user successfully makes (any
 * POST / PUT / PATCH / DELETE), so the Activity Logs page shows who did what and when.
 * Logins and logouts are logged by the auth controllers; failed or invalid submissions are not logged.
 */
class RecordActivity
{
    /** Route-name prefix => [category, noun]. First match wins, so list specific prefixes first. */
    private const AREAS = [
        'fees.payments' => ['payment', 'payment'],
        'fees.collect' => ['payment', 'class payment sheet'],
        'fees.reminders' => ['notice', 'fee reminders'],
        'fees.bulk-bill' => ['billing', 'bulk bill'],
        'fees.billing' => ['billing', 'term bills'],
        'fees.charges' => ['billing', 'billed item'],
        'fees.arrears' => ['billing', 'opening balance'],
        'fees.categories' => ['billing', 'fee item'],
        'fee-structures' => ['billing', 'fee schedule'],
        'parent-notices' => ['notice', 'results/fees message to parents'],
        'sms' => ['sms', 'SMS'],
        'semesters.notify-parents' => ['notice', 'report links to parents'],
        'students.send-grades' => ['notice', 'report link to parent'],
        'scores' => ['scores', 'score sheet'],
        'term-reports' => ['scores', 'attendance & remarks'],
        'results' => ['scores', 'result'],
        'continuous-assessment' => ['scores', 'continuous assessment'],
        'assessment-settings' => ['settings', 'score sheet design'],
        'settings' => ['settings', 'setting'],
        'students' => ['pupil', 'pupil'],
        'class-groups' => ['class', 'class'],
        'promotions' => ['class', 'promotion'],
        'subjects' => ['academic', 'subject'],
        'teachers' => ['academic', 'teacher'],
        'semesters' => ['academic', 'term'],
        'timetable' => ['academic', 'timetable'],
        'quizzes' => ['academic', 'quiz'],
        'assignments' => ['academic', 'assignment'],
        'users' => ['user', 'staff account'],
        'roles' => ['user', 'role'],
        'profile' => ['user', 'profile'],
    ];

    private const VERBS = [
        'store' => 'Added', 'create' => 'Added', 'update' => 'Updated', 'save' => 'Saved', 'destroy' => 'Deleted', 'bulk-destroy' => 'Deleted several',
        'import' => 'Imported', 'send' => 'Sent', 'copy' => 'Copied', 'set-current' => 'Set current',
    ];

    /** Routes never logged: authentication is logged separately, and the rest are noise. */
    private const SKIP = ['login', 'logout', 'student.login', 'student.logout', 'password.', 'student.password.', 'search.', 'fees.bulk-bill.preview'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            report($e);   // logging must never break the action that was just performed
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        if ($request->isMethodSafe() || !$request->user() || $response->getStatusCode() >= 400) {
            return;
        }

        $name = $request->route()?->getName();
        if (!$name || Str::startsWith($name, self::SKIP)) {
            return;
        }

        // A form that bounced back with validation errors or an error message changed nothing.
        if ($request->hasSession() && ($request->session()->get('errors')?->any() || $request->session()->has('error'))) {
            return;
        }

        [$category, $noun] = $this->area($name);
        $subject = $this->subject($request);

        $description = trim($this->verb($name, $request) . " {$noun}" . ($subject ? ": {$subject}" : '') . $this->detail($name, $request));

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'user_type' => get_class($request->user()),
            'action' => $category,
            'description' => Str::limit($description, 480),
            'route_name' => $name,
            'method' => $request->method(),
            'subject' => $subject ? Str::limit($subject, 250) : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    private function area(string $name): array
    {
        foreach (self::AREAS as $prefix => $area) {
            if ($name === $prefix || Str::startsWith($name, $prefix . '.')) {
                return $area;
            }
        }

        return ['other', Str::of($name)->before('.')->replace('-', ' ')->toString()];
    }

    private function verb(string $name, Request $request): string
    {
        $last = Str::afterLast($name, '.');

        return self::VERBS[$last] ?? match ($request->method()) {
            'DELETE' => 'Deleted',
            'POST' => Str::ucfirst(str_replace('-', ' ', $last)),
            default => 'Updated',
        };
    }

    /** What was acted on: the first model in the route (pupil, payment, class...) by its name. */
    private function subject(Request $request): ?string
    {
        foreach ((array) $request->route()?->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter->full_name ?? $parameter->receipt_number ?? $parameter->name ?? $parameter->title ?? $parameter->label ?? null;
            }
        }

        return null;
    }

    /** A little extra for money and messages, never anything sensitive. */
    private function detail(string $name, Request $request): string
    {
        return match (true) {
            $name === 'fees.payments.store' && is_numeric($request->input('amount')) => ' (GH₵ ' . number_format((float) $request->input('amount'), 2) . ')',
            $name === 'fees.collect.store' => ' (' . collect($request->input('rows', []))->filter(fn ($r) => (float) ($r['amount'] ?? 0) > 0)->count() . ' pupils)',
            $name === 'fees.bulk-bill.store' => ' (' . ($request->boolean('is_discount') ? 'discount' : ($request->input('category') ?: 'item')) . ', ' . $request->input('target') . ')',
            $name === 'parent-notices.send' => ' (' . implode('+', (array) $request->input('kinds')) . ' by ' . implode('+', (array) $request->input('channels')) . ')',
            default => '',
        };
    }
}
