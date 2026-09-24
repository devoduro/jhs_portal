<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Assignment</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:8px; padding:32px; border:1px solid #e5e7eb;">
            <h1 style="font-size:18px; color:#111827; margin:0 0 4px;">{{ \App\Models\Setting::get('institution_name', 'Your School') }}</h1>
            <p style="color:#6b7280; font-size:13px; margin:0 0 24px;">Online Portal Notification</p>

            <h2 style="font-size:16px; color:#111827; margin:0 0 12px;">New Assignment: {{ $assignment->title }}</h2>

            <p style="color:#374151; font-size:14px; line-height:1.5;">
                Dear {{ $student->full_name }},<br><br>
                Your teacher has posted a new assignment for <strong>{{ $assignment->subject->code ?? '' }} - {{ $assignment->subject->title ?? '' }}</strong>.
            </p>

            <table style="width:100%; font-size:13px; color:#374151; margin:16px 0; border-collapse:collapse;">
                <tr>
                    <td style="padding:6px 0; color:#6b7280;">Due</td>
                    <td style="padding:6px 0; text-align:right; font-weight:bold;">{{ $assignment->due_at->format('l, M j, Y g:i A') }}</td>
                </tr>
                @if($assignment->max_score)
                <tr>
                    <td style="padding:6px 0; color:#6b7280;">Max Score</td>
                    <td style="padding:6px 0; text-align:right; font-weight:bold;">{{ $assignment->max_score }}</td>
                </tr>
                @endif
            </table>

            <div style="text-align:center; margin:28px 0;">
                <a href="{{ route('student.login') }}" style="background:#2563eb; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; font-size:14px; display:inline-block;">
                    Log In to View Assignment
                </a>
            </div>

            <p style="color:#9ca3af; font-size:12px; line-height:1.5; margin-top:24px; border-top:1px solid #e5e7eb; padding-top:16px;">
                Log in with your index number ({{ $student->index_number }}) and your usual password to view the full assignment details and submit your work.
            </p>
        </div>
        <p style="text-align:center; color:#9ca3af; font-size:12px; margin-top:16px;">
            &copy; {{ date('Y') }} {{ \App\Models\Setting::get('institution_name', '') }}
        </p>
    </div>
</body>
</html>
