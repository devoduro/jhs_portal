<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Fee Statement</title></head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
@php $money = fn ($v) => 'GHS ' . number_format((float) $v, 2); @endphp
@include('emails._shell-start', ['tagline' => $term->name . ' Fee Statement' . ($term->academicYear ? ' · ' . $term->academicYear->name : '')])
        <p style="color:#374151; font-size:14px; line-height:1.5;">
            Dear {{ $student->emergency_contact_name ?: 'Parent/Guardian' }},<br><br>
            This is the fee statement for <strong>{{ $student->full_name }}</strong>
            ({{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) }}, {{ $student->index_number }}) for {{ $term->name }}.
        </p>

        <table style="width:100%; border-collapse:collapse; font-size:13px; margin:12px 0;">
            <tr style="background:#f9fafb; color:#6b7280; text-align:left;"><th style="padding:8px;">Fee item</th><th style="padding:8px; text-align:right;">Amount</th></tr>
            @forelse($items as $item)
                <tr style="border-top:1px solid #e5e7eb; color:#374151;">
                    <td style="padding:8px;">{{ $item->categoryLabel() }}</td>
                    <td style="padding:8px; text-align:right; {{ $item->amount < 0 ? 'color:#16a34a;' : '' }}">{{ $money($item->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" style="padding:8px; color:#9ca3af;">Nothing has been billed for this term yet.</td></tr>
            @endforelse
        </table>

        @if($payments->isNotEmpty())
            <table style="width:100%; border-collapse:collapse; font-size:13px; margin:12px 0;">
                <tr style="background:#f9fafb; color:#6b7280; text-align:left;"><th style="padding:8px;">Payment received</th><th style="padding:8px;">Receipt</th><th style="padding:8px; text-align:right;">Amount</th></tr>
                @foreach($payments as $payment)
                    <tr style="border-top:1px solid #e5e7eb; color:#374151;">
                        <td style="padding:8px;">{{ $payment->payment_date->format('d M Y') }} · {{ $payment->methodLabel() }}</td>
                        <td style="padding:8px;">{{ $payment->receipt_number }}</td>
                        <td style="padding:8px; text-align:right;">{{ $money($payment->amount) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <table style="width:100%; font-size:14px; color:#374151; border-collapse:collapse; border-top:2px solid #e5e7eb;">
            @if($fees['brought_forward'] > 0.009 || $fees['brought_forward'] < -0.009)
                <tr><td style="padding:6px 0; color:#6b7280;">Balance brought forward</td><td style="padding:6px 0; text-align:right;">{{ $money($fees['brought_forward']) }}</td></tr>
            @endif
            <tr><td style="padding:6px 0; color:#6b7280;">Billed this term</td><td style="padding:6px 0; text-align:right;">{{ $money($fees['term_bill']) }}</td></tr>
            <tr><td style="padding:6px 0; color:#6b7280;">Paid this term</td><td style="padding:6px 0; text-align:right;">{{ $money($fees['paid']) }}</td></tr>
            <tr style="font-weight:bold; font-size:16px;">
                <td style="padding:8px 0;">{{ $fees['balance'] > 0.009 ? 'Total owing' : ($fees['balance'] < -0.009 ? 'In credit' : 'Balance') }}</td>
                <td style="padding:8px 0; text-align:right; color:{{ $fees['balance'] > 0.009 ? '#dc2626' : '#16a34a' }};">{{ $money(abs($fees['balance'])) }}</td>
            </tr>
            @if($fees['next_term_fees'] > 0)
                <tr><td style="padding:6px 0; color:#6b7280;">Fees for next term</td><td style="padding:6px 0; text-align:right;">{{ $money($fees['next_term_fees']) }}</td></tr>
            @endif
        </table>

        @if($fees['balance'] > 0.009)
            <p style="color:#374151; font-size:13px; line-height:1.5; margin-top:16px;">Kindly settle the outstanding balance at the school's accounts office. Thank you.</p>
        @endif
@include('emails._shell-end')
</body></html>
