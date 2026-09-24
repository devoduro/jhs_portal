<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terminal Reports — {{ $cards->first()['classGroup']->name ?? '' }} ({{ $cards->count() }})</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @include('report-card._print-styles')
</head>
<body class="bg-gray-100 font-sans">
    <div class="no-print bg-white border-b border-gray-200 px-6 py-3 flex justify-between items-center sticky top-0 z-20 shadow-sm">
        <span class="text-sm text-gray-600">
            {{ $cards->first()['classGroup']->name ?? '' }} — {{ $cards->first()['term']->full_name ?? '' }}:
            {{ $cards->count() }} report(s), one per A4 page
        </span>
        <button onclick="window.print()" class="text-white rounded-md px-4 py-2 text-sm" style="background:#2563eb;">
            Print / Save as PDF
        </button>
    </div>

    @foreach($cards as $card)
        <div class="report-card-page">
            @include('report-card._card', $card)
        </div>
    @endforeach
</body>
</html>
