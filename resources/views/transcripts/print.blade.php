<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Academic Transcript{{ $single ? ' — ' . $single->full_name : 's' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        .sheet { width: 210mm; min-height: 297mm; margin: 1.5rem auto; padding: 12mm; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
        .year { break-inside: avoid; page-break-inside: avoid; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .sheet { margin: 0; padding: 0; width: auto; min-height: 0; box-shadow: none; page-break-after: always; }
            .sheet:last-child { page-break-after: auto; }
        }
        @media screen and (max-width: 820px) { .sheet { width: auto; min-height: 0; margin: 12px; padding: 12px; } }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-900">
@php
    $fmt = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $ord = fn ($n) => \App\Services\TerminalReportService::ordinal($n);
    $offered = \App\Support\FormLabel::options();
@endphp

<div class="no-print sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 border-b bg-white px-6 py-3 shadow-sm">
    @if($single)
        <form method="GET" action="{{ route('students.transcript', $single) }}" class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ route('students.show', $single) }}" class="mr-2 text-gray-600 hover:text-blue-600"><i class="fas fa-arrow-left mr-1"></i> {{ $single->full_name }}</a>
            <span class="text-gray-500">From</span>
            <select name="from" class="rounded border px-2 py-1" onchange="this.form.submit()">
                <option value="">first class</option>
                @foreach($offered as $level => $label)<option value="{{ $level }}" @selected($from === $level)>{{ $label }}</option>@endforeach
            </select>
            <span class="text-gray-500">to</span>
            <select name="to" class="rounded border px-2 py-1" onchange="this.form.submit()">
                <option value="">latest class</option>
                @foreach($offered as $level => $label)<option value="{{ $level }}" @selected($to === $level)>{{ $label }}</option>@endforeach
            </select>
        </form>
    @else
        <a href="{{ route('transcripts.index') }}" class="text-sm text-gray-600 hover:text-blue-600"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <span class="text-sm text-gray-600">{{ $transcripts->count() }} transcript(s), one pupil per page</span>
    @endif
    <button onclick="window.print()" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"><i class="fas fa-print mr-1"></i> Print / Save as PDF</button>
</div>

@foreach($transcripts as $t)
    @php $s = $t['student']; @endphp
    <div class="sheet text-[12px] leading-snug">
        @include('partials.letterhead', ['settings' => $settings, 'title' => 'Academic Transcript', 'logoSize' => 64])

        <div class="mb-4 flex gap-3">
            <table class="flex-1 border-collapse">
                <tr>
                    <td class="w-32 border border-gray-400 bg-gray-100 px-2 py-1 font-semibold">Name</td>
                    <td class="border border-gray-400 px-2 py-1 font-bold uppercase" colspan="3">{{ $s->full_name }}</td>
                </tr>
                <tr>
                    <td class="border border-gray-400 bg-gray-100 px-2 py-1 font-semibold">Admission No.</td>
                    <td class="border border-gray-400 px-2 py-1">{{ $s->index_number }}</td>
                    <td class="w-28 border border-gray-400 bg-gray-100 px-2 py-1 font-semibold">Gender</td>
                    <td class="border border-gray-400 px-2 py-1">{{ $s->gender }}</td>
                </tr>
                <tr>
                    <td class="border border-gray-400 bg-gray-100 px-2 py-1 font-semibold">Date of Birth</td>
                    <td class="border border-gray-400 px-2 py-1">{{ $s->date_of_birth?->format('d/m/Y') ?? '—' }}</td>
                    <td class="border border-gray-400 bg-gray-100 px-2 py-1 font-semibold">Status</td>
                    <td class="border border-gray-400 px-2 py-1">
                        {{ $s->status === 'graduated' ? 'Completed' . ($s->graduation_year ? ' (' . $s->graduation_year . ')' : '') : ($s->status === 'withdrawn' ? 'Withdrawn' : 'In ' . ($s->classGroup->name ?? \App\Support\FormLabel::label($s->level))) }}
                    </td>
                </tr>
                <tr>
                    <td class="border border-gray-400 bg-gray-100 px-2 py-1 font-semibold">Record covers</td>
                    <td class="border border-gray-400 px-2 py-1 font-semibold" colspan="3">
                        @if($t['years']->isNotEmpty())
                            {{ $t['years']->first()['label'] }}@if($t['years']->count() > 1) to {{ $t['years']->last()['label'] }}@endif
                            ({{ $t['years']->first()['academic_year'] }}@if($t['years']->count() > 1) – {{ $t['years']->last()['academic_year'] }}@endif)
                        @else
                            —
                        @endif
                    </td>
                </tr>
            </table>
            <x-student-photo :student="$s" class="h-32 w-28 border border-gray-400 object-cover" />
        </div>

        @forelse($t['years'] as $year)
            <div class="year mb-4">
                <div class="flex items-center justify-between bg-gray-800 px-2 py-1 font-semibold text-white">
                    <span>{{ $year['label'] }} &mdash; {{ $year['class'] }}</span>
                    <span>{{ $year['academic_year'] }}</span>
                </div>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-400 px-2 py-1 text-left">Subject</th>
                            @foreach($year['terms'] as $term)
                                <th class="w-16 border border-gray-400 px-1 py-1">{{ \Illuminate\Support\Str::before($term->name, ' Term') }} Term</th>
                            @endforeach
                            <th class="w-16 border border-gray-400 px-1 py-1">Average</th>
                            <th class="w-12 border border-gray-400 px-1 py-1">Grade</th>
                            <th class="border border-gray-400 px-2 py-1 text-left">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($year['subjects'] as $subject)
                            <tr>
                                <td class="border border-gray-400 px-2 py-0.5">{{ $subject['title'] }}</td>
                                @foreach($year['terms'] as $term)
                                    <td class="border border-gray-400 px-1 py-0.5 text-center">{{ $fmt($subject['terms'][$term->id] ?? null) }}</td>
                                @endforeach
                                <td class="border border-gray-400 px-1 py-0.5 text-center font-semibold">{{ $fmt($subject['average']) }}</td>
                                <td class="border border-gray-400 px-1 py-0.5 text-center font-semibold">{{ $subject['grade'] }}</td>
                                <td class="border border-gray-400 px-2 py-0.5">{{ $subject['remark'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr class="font-semibold">
                            <td class="border border-gray-400 px-2 py-0.5">Term average</td>
                            @foreach($year['termSummaries'] as $ts)
                                <td class="border border-gray-400 px-1 py-0.5 text-center">{{ $fmt($ts['average']) }}</td>
                            @endforeach
                            <td class="border border-gray-400 px-1 py-0.5 text-center">{{ $fmt($year['average']) }}</td>
                            <td class="border border-gray-400" colspan="2"></td>
                        </tr>
                        <tr>
                            <td class="border border-gray-400 px-2 py-0.5">Position in class</td>
                            @foreach($year['termSummaries'] as $ts)
                                <td class="border border-gray-400 px-1 py-0.5 text-center">{{ $ts['position'] ? $ord($ts['position']) . '/' . $ts['roll'] : '' }}</td>
                            @endforeach
                            <td class="border border-gray-400" colspan="3"></td>
                        </tr>
                        <tr>
                            <td class="border border-gray-400 px-2 py-0.5">Attendance</td>
                            @foreach($year['termSummaries'] as $ts)
                                <td class="border border-gray-400 px-1 py-0.5 text-center">{{ $ts['days_present'] !== null ? $ts['days_present'] . ($ts['attendance_days'] ? '/' . $ts['attendance_days'] : '') : '' }}</td>
                            @endforeach
                            <td class="border border-gray-400 px-2 py-0.5" colspan="3">
                                @if($year['promoted_to'] === 0) Completed {{ $year['label'] }}
                                @elseif($year['promoted_to'] === $year['level']) Repeated {{ $year['label'] }}
                                @elseif($year['promoted_to']) Promoted to {{ \App\Support\FormLabel::label($year['promoted_to']) }}
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @empty
            <p class="py-10 text-center text-gray-500">No scores recorded for {{ $s->full_name }} in the selected classes.</p>
        @endforelse

        @if($t['years']->count() > 1)
            <div class="year mb-4">
                <div class="bg-gray-800 px-2 py-1 font-semibold text-white">Summary</div>
                <table class="w-full border-collapse">
                    <tr class="bg-gray-100">
                        @foreach($t['years'] as $year)<th class="border border-gray-400 px-1 py-1">{{ $year['label'] }}</th>@endforeach
                        <th class="border border-gray-400 px-1 py-1">Overall</th>
                    </tr>
                    <tr>
                        @foreach($t['years'] as $year)<td class="border border-gray-400 px-1 py-1 text-center">{{ $fmt($year['average']) }}%</td>@endforeach
                        <td class="border border-gray-400 px-1 py-1 text-center font-bold">{{ $fmt($t['overall']) }}%</td>
                    </tr>
                </table>
            </div>
        @endif

        <div class="mt-10 flex items-end justify-between text-[11px] text-gray-700">
            <div>
                <div class="mb-1 w-56 border-t border-gray-600"></div>
                {{ $settings['head_title'] ?? 'Head Teacher' }}'s signature &amp; stamp
            </div>
            <div class="text-right">
                Date issued: {{ now()->format('d/m/Y') }}
                <p class="mt-1 text-[10px] text-gray-500">This transcript is not valid without the school stamp.</p>
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
