<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Cards ({{ $cards->count() }} students)</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .report-card-page { page-break-after: always; }
            .report-card-page:last-child { page-break-after: auto; }
        }
        .report-card-page:not(:last-child) {
            border-bottom: 2px dashed #cbd5e1;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="no-print bg-white border-b border-gray-200 px-6 py-3 flex justify-between items-center sticky top-0 z-20 shadow-sm">
        <span class="text-sm text-gray-600">{{ $cards->count() }} report card(s) &mdash; one per page when printed</span>
        <button onclick="window.print()" class="bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700" style="background:#2563eb;">
            Print / Save as PDF
        </button>
    </div>

    @foreach($cards as $data)
        <div class="report-card-page max-w-4xl mx-auto p-8 print:p-0 print:max-w-none bg-white my-6 print:my-0 print:shadow-none shadow-md rounded-lg">
            @include('report-card._card', $data)
        </div>
    @endforeach
</body>
</html>
