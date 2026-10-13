<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Terminal Report</title></head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
@include('emails._shell-start', ['tagline' => $term->name . ' Terminal Report' . ($term->academicYear ? ' · ' . $term->academicYear->name : '')])
        <p style="color:#374151; font-size:14px; line-height:1.5;">
            Dear {{ $student->emergency_contact_name ?: 'Parent/Guardian' }},<br><br>
            Here are <strong>{{ $student->full_name }}</strong>'s results for {{ $term->name }}
            ({{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) }}).
        </p>

        <table style="width:100%; border-collapse:collapse; font-size:13px; margin:14px 0;">
            <tr style="background:#f9fafb; color:#6b7280; text-align:left;">
                <th style="padding:8px;">Subject</th>
                <th style="padding:8px; text-align:right;">Class</th>
                <th style="padding:8px; text-align:right;">Exam</th>
                <th style="padding:8px; text-align:right;">Total</th>
                <th style="padding:8px; text-align:center;">Grade</th>
                <th style="padding:8px; text-align:left;">Remark</th>
            </tr>
            @foreach($report['rows'] as $row)
                @continue(!$row['result'])
                <tr style="border-top:1px solid #e5e7eb; color:#374151;">
                    <td style="padding:8px;">{{ $row['subject']->title }}</td>
                    <td style="padding:8px; text-align:right;">{{ $row['class_score'] !== null ? (float) $row['class_score'] : '-' }}</td>
                    <td style="padding:8px; text-align:right;">{{ $row['exam_score'] !== null ? (float) $row['exam_score'] : '-' }}</td>
                    <td style="padding:8px; text-align:right; font-weight:bold;">{{ (float) $row['total'] }}</td>
                    <td style="padding:8px; text-align:center;">{{ $row['grade'] }}</td>
                    <td style="padding:8px;">{{ $row['remark'] }}</td>
                </tr>
            @endforeach
        </table>

        <table style="width:100%; font-size:13px; color:#374151; border-collapse:collapse;">
            <tr><td style="padding:5px 0; color:#6b7280;">Total score</td><td style="padding:5px 0; text-align:right; font-weight:bold;">{{ $report['total'] !== null ? (float) $report['total'] : '-' }}</td></tr>
            <tr><td style="padding:5px 0; color:#6b7280;">Average</td><td style="padding:5px 0; text-align:right; font-weight:bold;">{{ $report['average'] !== null ? $report['average'] . '%' : '-' }}</td></tr>
            <tr><td style="padding:5px 0; color:#6b7280;">Position in class</td><td style="padding:5px 0; text-align:right; font-weight:bold;">{{ \App\Services\TerminalReportService::ordinal($report['position']) }} out of {{ $roll }}</td></tr>
            @if($report['aggregate'] !== null)
                <tr><td style="padding:5px 0; color:#6b7280;">Aggregate</td><td style="padding:5px 0; text-align:right; font-weight:bold;">{{ $report['aggregate'] }}</td></tr>
            @endif
            @if($term->next_term_begins)
                <tr><td style="padding:5px 0; color:#6b7280;">Next term begins</td><td style="padding:5px 0; text-align:right; font-weight:bold;">{{ $term->next_term_begins->format('l, j F Y') }}</td></tr>
            @endif
        </table>

        <div style="text-align:center; margin:26px 0 4px;">
            <a href="{{ $link }}" style="background:#2563eb; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; font-size:14px; display:inline-block;">View / Print Full Report Card</a>
        </div>
        <p style="text-align:center; color:#9ca3af; font-size:12px; margin:6px 0 0;">The link works without logging in and expires in 30 days.</p>
@include('emails._shell-end')
</body></html>
