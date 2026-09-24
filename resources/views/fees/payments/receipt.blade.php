<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $payment->receipt_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page { size: A4; margin: 10mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .sheet { box-shadow: none !important; margin: 0 !important; }
        }
        .cut { border-top: 2px dashed #9ca3af; }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-900">
    <div class="no-print bg-white border-b px-6 py-3 flex justify-between items-center sticky top-0 shadow-sm">
        <a href="{{ route('fees.show', ['student' => $student, 'semester_id' => $payment->semester_id]) }}" class="text-sm text-gray-600 hover:text-blue-600">
            <i class="fas fa-arrow-left mr-1"></i> Back to fee account
        </a>
        <button onclick="window.print()" class="bg-blue-600 text-white rounded-md px-4 py-2 text-sm hover:bg-blue-700">
            <i class="fas fa-print mr-1"></i> Print Receipt
        </button>
    </div>

    <div class="sheet max-w-3xl mx-auto bg-white my-6 shadow-md p-6">
        @foreach(['PARENT\'S COPY', 'OFFICE COPY'] as $copy)
            @if(!$loop->first)
                <div class="cut my-6 relative"><span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white px-2 text-xs text-gray-400"><i class="fas fa-cut"></i> cut here</span></div>
            @endif

            <div>
                @include('partials.letterhead', ['settings' => $settings, 'title' => 'Official Receipt', 'logoSize' => 56])

                <div class="flex justify-between text-sm mb-3">
                    <p><span class="text-gray-500">Receipt No:</span> <span class="font-bold text-red-700">{{ $payment->receipt_number }}</span></p>
                    <p class="text-xs font-semibold text-gray-500">{{ $copy }}</p>
                    <p><span class="text-gray-500">Date:</span> <span class="font-semibold">{{ $payment->payment_date->format('d/m/Y') }}</span></p>
                </div>

                <table class="w-full text-sm border border-gray-300">
                    <tr class="border-b border-gray-300">
                        <td class="px-3 py-2 w-40 bg-gray-50 font-semibold">Received from</td>
                        <td class="px-3 py-2">{{ $payment->paid_by ?: ($student->emergency_contact_name ?: 'Parent/Guardian') }}</td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="px-3 py-2 bg-gray-50 font-semibold">Pupil</td>
                        <td class="px-3 py-2">{{ $student->full_name }} <span class="text-gray-500">({{ $student->index_number }})</span></td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="px-3 py-2 bg-gray-50 font-semibold">Class</td>
                        <td class="px-3 py-2">{{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) }}</td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="px-3 py-2 bg-gray-50 font-semibold">Being payment of</td>
                        <td class="px-3 py-2">School fees — {{ $payment->semester?->full_name ?? ($payment->academicYear->name ?? '') }}{{ $payment->notes ? ' (' . $payment->notes . ')' : '' }}</td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="px-3 py-2 bg-gray-50 font-semibold">Amount in words</td>
                        <td class="px-3 py-2 italic">{{ \App\Support\Money::words((float) $payment->amount) }}</td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="px-3 py-2 bg-gray-50 font-semibold">Mode of payment</td>
                        <td class="px-3 py-2">
                            {{ $payment->methodLabel() }}{{ $payment->bank ? ' — ' . $payment->bank : '' }}{{ $payment->reference_number ? ' (Ref: ' . $payment->reference_number . ')' : '' }}
                        </td>
                    </tr>
                </table>

                <div class="flex justify-between items-end mt-4">
                    <div class="border-2 border-gray-800 px-4 py-2">
                        <p class="text-xs text-gray-500">AMOUNT PAID</p>
                        <p class="text-2xl font-extrabold">GH₵ {{ number_format($payment->amount, 2) }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="text-gray-500">Balance after this payment</p>
                        <p class="text-lg font-bold {{ $balanceAfter > 0 ? 'text-red-700' : 'text-green-700' }}">
                            GH₵ {{ number_format($balanceAfter, 2) }}{{ $balanceAfter < 0 ? ' (credit)' : '' }}
                        </p>
                    </div>
                </div>

                <div class="flex justify-between items-end mt-8 text-xs text-gray-600">
                    <p>Received by: <span class="font-semibold text-gray-800">{{ $payment->recordedBy->name ?? '________________' }}</span></p>
                    <div class="text-center">
                        <div class="border-t border-gray-500 w-44 mb-1"></div>
                        <p>Accounts Officer / Stamp</p>
                    </div>
                </div>
                <p class="text-center text-[10px] text-gray-400 mt-3">Fees paid are not refundable. Please keep this receipt safely.</p>
            </div>
        @endforeach
    </div>
</body>
</html>
