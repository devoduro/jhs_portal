<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Broadsheet — {{ $classGroup->name }} — {{ $term->full_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        th.vertical { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-900">
    <div class="no-print bg-white border-b px-6 py-3 flex justify-between items-center sticky top-0 shadow-sm">
        <a href="{{ route('scores.index', ['class_group_id' => $classGroup->id, 'semester_id' => $term->id]) }}" class="text-sm text-gray-600"><i class="fas fa-arrow-left mr-1"></i> Back to score entry</a>
        <button onclick="window.print()" class="bg-blue-600 text-white rounded-md px-4 py-2 text-sm"><i class="fas fa-print mr-1"></i> Print</button>
    </div>

    <div class="bg-white mx-auto my-6 p-6 max-w-[1400px]">
        @include('partials.letterhead', ['settings' => $settings, 'title' => 'Class Broadsheet', 'logoSize' => 50])
        <div class="flex justify-between text-sm mb-3">
            <p><strong>Class:</strong> {{ $classGroup->name }}</p>
            <p><strong>Term:</strong> {{ $term->full_name }}</p>
            <p><strong>No. on roll:</strong> {{ $roll }}</p>
            <p><strong>Class average:</strong> {{ $classAverage ?? '—' }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse border border-gray-400">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border border-gray-400 px-1 py-1">Pos.</th>
                        <th class="border border-gray-400 px-2 py-1 text-left">Pupil</th>
                        @foreach($subjects as $subject)
                            <th class="border border-gray-400 px-1 py-2 vertical" title="{{ $subject->title }}">{{ \Illuminate\Support\Str::limit($subject->title, 22) }}</th>
                        @endforeach
                        <th class="border border-gray-400 px-1 py-1">Total</th>
                        <th class="border border-gray-400 px-1 py-1">Avg.</th>
                        @if($isJhs)<th class="border border-gray-400 px-1 py-1">Agg.</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($pupils as $pupil)
                        <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                            <td class="border border-gray-400 px-1 py-1 text-center font-semibold">{{ \App\Services\TerminalReportService::ordinal($pupil['position']) }}</td>
                            <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $pupil['student']->full_name }}</td>
                            @foreach($pupil['rows'] as $row)
                                <td class="border border-gray-400 px-1 py-1 text-center">{{ $row['total'] !== null ? rtrim(rtrim(number_format($row['total'], 1), '0'), '.') : '' }}</td>
                            @endforeach
                            <td class="border border-gray-400 px-1 py-1 text-center font-semibold">{{ $pupil['total'] !== null ? rtrim(rtrim(number_format($pupil['total'], 1), '0'), '.') : '' }}</td>
                            <td class="border border-gray-400 px-1 py-1 text-center">{{ $pupil['average'] }}</td>
                            @if($isJhs)<td class="border border-gray-400 px-1 py-1 text-center">{{ $pupil['aggregate'] ?? '' }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-3">Printed {{ now()->format('d M Y, g:i A') }}</p>
    </div>
</body>
</html>
