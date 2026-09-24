{{-- One pupil's terminal report (A4 portrait). Data comes from ReportCardController::decorate(). --}}
@php
    $s = $student;
    $tr = $term_report;
    $fmt = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $ord = fn ($n) => \App\Services\TerminalReportService::ordinal($n);
    $attendanceDays = $term->attendance_days;
@endphp

<div class="report-card text-[12px] leading-snug text-gray-900">
    @include('partials.letterhead', ['settings' => $settings, 'title' => "Pupil's Terminal Report", 'logoSize' => 64])

    <!-- Pupil details -->
    <div class="flex gap-3 mb-3">
        <table class="flex-1 border-collapse">
            <tr>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold w-28">Name</td>
                <td class="border border-gray-400 px-2 py-1 font-bold uppercase" colspan="3">{{ $s->full_name }}</td>
            </tr>
            <tr>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Admission No.</td>
                <td class="border border-gray-400 px-2 py-1">{{ $s->index_number }}</td>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold w-28">Class</td>
                <td class="border border-gray-400 px-2 py-1">{{ $classGroup->name }}</td>
            </tr>
            <tr>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Term</td>
                <td class="border border-gray-400 px-2 py-1">{{ $term->name }}</td>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Academic Year</td>
                <td class="border border-gray-400 px-2 py-1">{{ $term->academicYear->name ?? '' }}</td>
            </tr>
            <tr>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">No. on Roll</td>
                <td class="border border-gray-400 px-2 py-1">{{ $roll }}</td>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Position</td>
                <td class="border border-gray-400 px-2 py-1 font-bold">{{ $position ? $ord($position) . ' out of ' . $roll : '—' }}</td>
            </tr>
            <tr>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Attendance</td>
                <td class="border border-gray-400 px-2 py-1">
                    {{ $tr?->days_present !== null ? $tr->days_present : '___' }}{{ $attendanceDays ? ' out of ' . $attendanceDays : '' }}
                </td>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Vacation Date</td>
                <td class="border border-gray-400 px-2 py-1">{{ $term->vacation_date?->format('d/m/Y') ?? ($term->end_date?->format('d/m/Y') ?? '') }}</td>
            </tr>
            <tr>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Gender</td>
                <td class="border border-gray-400 px-2 py-1">{{ $s->gender }}</td>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Next Term Begins</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $term->next_term_begins?->format('d/m/Y') ?? '' }}</td>
            </tr>
        </table>
        <x-student-photo :student="$s" class="w-28 h-32 object-cover border border-gray-400" />
    </div>

    <!-- Scores -->
    <table class="w-full border-collapse mb-2">
        <thead>
            <tr class="bg-gray-800 text-white">
                <th class="border border-gray-500 px-2 py-1 text-left">Subject</th>
                <th class="border border-gray-500 px-1 py-1 w-16">Class Score ({{ $fmt($classMax) }})</th>
                <th class="border border-gray-500 px-1 py-1 w-16">Exam Score ({{ $fmt($examMax) }})</th>
                <th class="border border-gray-500 px-1 py-1 w-14">Total (100)</th>
                <th class="border border-gray-500 px-1 py-1 w-12">Grade</th>
                <th class="border border-gray-500 px-1 py-1 w-14">Position</th>
                <th class="border border-gray-500 px-2 py-1 text-left">Remark</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                    <td class="border border-gray-400 px-2 py-1">{{ $row['subject']->title }}</td>
                    <td class="border border-gray-400 px-1 py-1 text-center">{{ $fmt($row['class_score']) }}</td>
                    <td class="border border-gray-400 px-1 py-1 text-center">{{ $fmt($row['exam_score']) }}</td>
                    <td class="border border-gray-400 px-1 py-1 text-center font-semibold">{{ $fmt($row['total']) }}</td>
                    <td class="border border-gray-400 px-1 py-1 text-center font-semibold">{{ $row['grade'] }}</td>
                    <td class="border border-gray-400 px-1 py-1 text-center">{{ $row['position'] ? $ord($row['position']) : '' }}</td>
                    <td class="border border-gray-400 px-2 py-1">{{ $row['remark'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-gray-100 font-semibold">
                <td class="border border-gray-400 px-2 py-1">Total Marks</td>
                <td class="border border-gray-400 px-1 py-1 text-center" colspan="3">{{ $fmt($total) ?: '—' }} <span class="font-normal text-gray-600">/ {{ $subjects_taken * 100 }}</span></td>
                <td class="border border-gray-400 px-2 py-1" colspan="3">
                    Average: {{ $average ?? '—' }}%
                    @if($isJhs)
                        &nbsp;&bull;&nbsp; Aggregate (best 6): {{ $aggregate ?? 'N/A' }}
                    @endif
                    @if($classAverage)
                        &nbsp;&bull;&nbsp; <span class="font-normal">Class average: {{ $classAverage }}%</span>
                    @endif
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Conduct -->
    <table class="w-full border-collapse mb-2">
        <tr>
            <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold w-24">Conduct</td>
            <td class="border border-gray-400 px-2 py-1">{{ $tr?->conduct }}</td>
            <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold w-24">Attitude</td>
            <td class="border border-gray-400 px-2 py-1">{{ $tr?->attitude }}</td>
            <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold w-24">Interest</td>
            <td class="border border-gray-400 px-2 py-1">{{ $tr?->interest }}</td>
        </tr>
    </table>

    <!-- Remarks -->
    <table class="w-full border-collapse mb-2">
        <tr>
            <td class="border border-gray-400 px-2 py-2 bg-gray-100 font-semibold w-40 align-top">Class Teacher's Remarks</td>
            <td class="border border-gray-400 px-2 py-2">
                <p class="min-h-[1.5rem] italic">{{ $tr?->class_teacher_remark }}</p>
                <p class="text-right text-[11px] text-gray-600 mt-3">
                    {{ $classGroup->classTeacher->name ?? '' }} &nbsp; Sign: ____________________
                </p>
            </td>
        </tr>
        <tr>
            <td class="border border-gray-400 px-2 py-2 bg-gray-100 font-semibold align-top">{{ $headTitle }}'s Remarks</td>
            <td class="border border-gray-400 px-2 py-2">
                <p class="min-h-[1.5rem] italic">{{ $tr?->head_teacher_remark }}</p>
                <p class="text-right text-[11px] text-gray-600 mt-3">Sign & Stamp: ____________________</p>
            </td>
        </tr>
        @if($isFinalTerm)
            <tr>
                <td class="border border-gray-400 px-2 py-1 bg-gray-100 font-semibold">Promotion</td>
                <td class="border border-gray-400 px-2 py-1 font-bold">
                    @if($tr?->promoted_to === 0)
                        Completed {{ \App\Support\FormLabel::label($classGroup->level) }}
                    @elseif($tr?->promoted_to === $classGroup->level)
                        Repeated in {{ \App\Support\FormLabel::label($classGroup->level) }}
                    @elseif($tr?->promoted_to)
                        Promoted to {{ \App\Support\FormLabel::label($tr->promoted_to) }}
                    @else
                        ______________________
                    @endif
                </td>
            </tr>
        @endif
    </table>

    <div class="flex gap-3 items-stretch">
        @if($showFees)
            <!-- Fees -->
            <table class="border-collapse flex-1">
                <tr><td colspan="2" class="border border-gray-400 px-2 py-1 bg-gray-800 text-white font-semibold">School Fees (GH₵)</td></tr>
                <tr>
                    <td class="border border-gray-400 px-2 py-0.5">Arrears brought forward</td>
                    <td class="border border-gray-400 px-2 py-0.5 text-right">{{ number_format($fees['brought_forward'], 2) }}</td>
                </tr>
                <tr>
                    <td class="border border-gray-400 px-2 py-0.5">This term's fees</td>
                    <td class="border border-gray-400 px-2 py-0.5 text-right">{{ number_format($fees['term_bill'], 2) }}</td>
                </tr>
                <tr>
                    <td class="border border-gray-400 px-2 py-0.5">Amount paid this term</td>
                    <td class="border border-gray-400 px-2 py-0.5 text-right">{{ number_format($fees['paid'], 2) }}</td>
                </tr>
                <tr class="font-bold">
                    <td class="border border-gray-400 px-2 py-0.5">Balance owing</td>
                    <td class="border border-gray-400 px-2 py-0.5 text-right {{ $fees['balance'] > 0 ? 'text-red-700' : '' }}">{{ number_format($fees['balance'], 2) }}</td>
                </tr>
                @if($fees['next_term_fees'] > 0)
                    <tr>
                        <td class="border border-gray-400 px-2 py-0.5">Next term's fees</td>
                        <td class="border border-gray-400 px-2 py-0.5 text-right">{{ number_format($fees['next_term_fees'], 2) }}</td>
                    </tr>
                @endif
            </table>
        @endif

        <!-- Grading key -->
        <table class="border-collapse {{ $showFees ? 'w-1/2' : 'w-full' }}">
            <tr><td colspan="3" class="border border-gray-400 px-2 py-1 bg-gray-800 text-white font-semibold">Grading Key</td></tr>
            @foreach($gradeBands as $band)
                @php $upper = $loop->first ? 100 : ((float) $gradeBands[$loop->index - 1]->min_score - 1); @endphp
                <tr class="text-[11px]">
                    <td class="border border-gray-400 px-2 py-0.5 text-center">{{ $fmt($band->min_score) }} – {{ $fmt($upper) }}</td>
                    <td class="border border-gray-400 px-2 py-0.5 text-center font-semibold">{{ $band->grade }}</td>
                    <td class="border border-gray-400 px-2 py-0.5">{{ $band->remark }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <p class="text-center text-[10px] text-gray-500 mt-2">This report is not valid without the school stamp. Printed {{ now()->format('d/m/Y') }}.</p>
</div>
