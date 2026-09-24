<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Card — {{ $student->full_name }}</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="no-print bg-white border-b border-gray-200 px-6 py-3 flex justify-between items-center sticky top-0 z-20 shadow-sm">
        @if($publicLink ?? true)
            <span class="text-sm text-gray-600">Report Card — shared securely, no login required</span>
        @else
            <a href="{{ route('students.show', $student) }}" class="text-sm text-gray-600 hover:text-primary-600">&larr; Back to {{ $student->full_name }}</a>
        @endif
        <button onclick="window.print()" class="bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700" style="background:#2563eb;">
            Print / Save as PDF
        </button>
    </div>

    <div class="max-w-4xl mx-auto p-8 print:p-0 print:max-w-none bg-white my-6 print:my-0 print:shadow-none shadow-md rounded-lg">
        @include('report-card._card')

        @if($publicLink ?? true)
            <p class="text-xs text-gray-400 text-center mt-8">This link was shared by {{ $institutionSettings['institution_name'] ?? 'the school' }} and is only valid for a limited time.</p>
        @endif
    </div>
</body>
</html>
