<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Your Password</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:8px; padding:32px; border:1px solid #e5e7eb;">
            <h1 style="font-size:18px; color:#111827; margin:0 0 4px;">{{ \App\Models\Setting::get('institution_name', 'Your School') }}</h1>
            <p style="color:#6b7280; font-size:13px; margin:0 0 24px;">Online Portal Notification</p>

            <h2 style="font-size:16px; color:#111827; margin:0 0 12px;">Reset Your Password</h2>

            <p style="color:#374151; font-size:14px; line-height:1.5;">
                Hello {{ $student->full_name }},<br><br>
                We received a request to reset the password for your student portal account ({{ $student->index_number }}). Click the button below to choose a new password. This link expires in 60 minutes.
            </p>

            <div style="text-align:center; margin:28px 0;">
                <a href="{{ $resetUrl }}" style="background:#2563eb; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; font-size:14px; display:inline-block;">
                    Reset My Password
                </a>
            </div>

            <p style="color:#9ca3af; font-size:12px; line-height:1.5; margin-top:24px; border-top:1px solid #e5e7eb; padding-top:16px;">
                If you did not request this, you can safely ignore this email - your password will not be changed.
            </p>
        </div>
        <p style="text-align:center; color:#9ca3af; font-size:12px; margin-top:16px;">
            &copy; {{ date('Y') }} {{ \App\Models\Setting::get('institution_name', '') }}
        </p>
    </div>
</body>
</html>
