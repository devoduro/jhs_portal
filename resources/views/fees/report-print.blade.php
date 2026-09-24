<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fee Collection Report — {{ $term->full_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page { size: A4; margin: 10mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; font-size: 11px; }
            .shadow-sm { box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-900">
    <div class="no-print bg-white border-b px-6 py-3 flex justify-between items-center sticky top-0 shadow-sm">
        <a href="{{ route('fees.report', request()->query()) }}" class="text-sm text-gray-600"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <button onclick="window.print()" class="bg-blue-600 text-white rounded-md px-4 py-2 text-sm"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
    <div class="max-w-5xl mx-auto bg-white my-6 p-8 space-y-6">
        @include('partials.letterhead', ['settings' => $settings, 'title' => 'Fee Collection Report'])
        <p class="text-center text-sm text-gray-600 -mt-2">{{ $term->full_name }} &bull; Printed {{ now()->format('d M Y, g:i A') }}</p>
        @include('fees.partials.report-body')
    </div>
</body>
</html>
