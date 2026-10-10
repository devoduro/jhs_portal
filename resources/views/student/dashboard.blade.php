@extends('components.student-app-layout')

@section('header')
    <div class="mb-6">
        <x-dashboard-hero :title="\App\Support\Greeting::salutation(\Illuminate\Support\Str::of($student->full_name)->explode(' ')->filter()->slice(1)->implode(' ') ?: $student->full_name)" :eyebrow="now()->format('l, j F Y')"
            :subtitle="($student->classGroup->name ?? \App\Support\FormLabel::label($student->level)) . ($currentSemester ? ' • ' . $currentSemester->full_name : '')">
            <x-slot:chips>
                <span><i class="fas fa-id-badge"></i> {{ $student->index_number }}</span>
                @if($student->classGroup?->classTeacher)<span><i class="fas fa-chalkboard-user"></i> {{ $student->classGroup->classTeacher->name }}</span>@endif
                @if($currentSemester?->next_term_begins)<span><i class="fas fa-calendar-check"></i> Next term begins {{ $currentSemester->next_term_begins->format('j M Y') }}</span>@endif
            </x-slot:chips>
            <a class="primary" href="{{ route('student.report-card') }}"><i class="fas fa-file-lines text-[#42a5dc]"></i> Terminal Reports</a>
            <a href="{{ route('student.fees.index') }}"><i class="fas fa-wallet"></i> Fees</a>
        </x-dashboard-hero>
    </div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile color="blue" icon="fa-users" title="My Class" :sub="\App\Support\FormLabel::label($student->level)" :value="$student->classGroup->name ?? 'Not placed'" />
        <x-stat-tile color="purple" icon="fa-ranking-star" title="Last Position"
            :sub="$latestReport ? $latestReport['term']->full_name : 'No report yet'"
            :value="$latestReport && $latestReport['position'] ? \App\Services\TerminalReportService::ordinal($latestReport['position']) . ' / ' . $latestReport['roll'] : '—'"
            :badge="$latestReport && $latestReport['average'] !== null ? 'Average ' . $latestReport['average'] . '%' : null"
            :href="$latestReport ? route('student.report-card', ['semester_id' => $latestReport['term']->id]) : null" />
        @if($isJhs)
            <x-stat-tile color="orange" icon="fa-chart-line" title="JHS Aggregate" sub="Best 6 subjects (lower is better)" :value="$aggregate ?? '—'" />
        @else
            <x-stat-tile color="orange" icon="fa-book" title="Subjects" sub="Taught in my class" :value="$currentSubjectIds->count()" />
        @endif
        <x-stat-tile :color="$balance > 0 ? 'red' : 'green'" icon="fa-wallet" title="School Fees"
            :sub="$balance > 0 ? 'Balance owing' : ($balance < 0 ? 'In credit' : 'Fully paid')"
            :value="'GH₵ ' . number_format(abs($balance), 2)"
            :href="route('student.fees.index')" />
    </div>

    @if($balance > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-red-50 p-4 ring-1 ring-red-100">
            <p class="text-sm text-red-800"><i class="fas fa-circle-exclamation mr-1"></i> There is an outstanding fees balance of <strong>GH₵ {{ number_format($balance, 2) }}</strong>. Please settle it at the school's accounts office.</p>
            <a href="{{ route('student.fees.index') }}" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700">View statement</a>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Latest scores -->
        <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100 lg:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-100 p-5">
                <h3 class="text-lg font-bold text-gray-800">{{ $latestReport ? $latestReport['term']->name . ' scores' : 'Scores' }}</h3>
                <a href="{{ route('student.report-card') }}" class="rounded-md bg-[#42a5dc] px-4 py-2 text-sm font-medium text-white hover:bg-[#2c8fc6]">Full report</a>
            </div>
            @php $latestResults = $latestReport ? $results->where('semester_id', $latestReport['term']->id) : collect(); @endphp
            @if($latestResults->isEmpty())
                <div class="p-12 text-center">
                    <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-blue-600"><i class="fas fa-clipboard-list text-2xl"></i></div>
                    <h3 class="mb-1 text-lg font-medium text-gray-900">No scores yet</h3>
                    <p class="text-gray-500">Your scores appear here once your teachers enter them.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-2 text-left font-medium">Subject</th>
                            <th class="px-3 py-2 text-center font-medium">Class</th>
                            <th class="px-3 py-2 text-center font-medium">Exam</th>
                            <th class="px-3 py-2 text-center font-medium">Total</th>
                            <th class="px-3 py-2 text-center font-medium">Grade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($latestResults->sortByDesc('score') as $result)
                            <tr>
                                <td class="px-5 py-2.5 text-gray-800">{{ $result->subject->title ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-center text-gray-600">{{ $result->class_score !== null ? (float) $result->class_score : '' }}</td>
                                <td class="px-3 py-2.5 text-center text-gray-600">{{ $result->exam_score !== null ? (float) $result->exam_score : '' }}</td>
                                <td class="px-3 py-2.5 text-center font-semibold">{{ (float) $result->score }}</td>
                                <td class="px-3 py-2.5 text-center"><span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">{{ $result->grade }}</span> <span class="hidden text-xs text-gray-400 sm:inline">{{ $result->remark }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Today + homework -->
        <div class="space-y-6">
            <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center justify-between border-b border-gray-100 p-5">
                    <h3 class="text-lg font-bold text-gray-800">Today</h3>
                    <a href="{{ route('student.timetable') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">Timetable</a>
                </div>
                @if($todayEntries->isEmpty())
                    <p class="p-6 text-center text-sm text-gray-500">No lessons on the timetable for {{ now()->format('l') }}.</p>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($todayEntries as $entry)
                            <div class="flex items-center justify-between px-5 py-3 text-sm">
                                <span class="font-medium text-gray-800">{{ $entry->subject->title ?? '' }}</span>
                                <span class="text-gray-500">{{ substr($entry->start_time, 0, 5) }} – {{ substr($entry->end_time, 0, 5) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <a href="{{ $pendingAssignmentsCount > 0 ? route('student.assignments.index') : route('student.quizzes.index') }}" class="block rounded-md bg-white p-5 shadow-sm ring-1 ring-gray-100 hover:shadow-md">
                <h3 class="text-sm font-semibold text-gray-700">Homework to do</h3>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $pendingQuizzesCount + $pendingAssignmentsCount }}</p>
                <p class="text-xs text-gray-500">{{ $pendingQuizzesCount }} quiz(zes) • {{ $pendingAssignmentsCount }} assignment(s)</p>
            </a>
        </div>
    </div>
</div>

    <!-- Password Change Modal -->
    @if(session('show_password_change'))
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="passwordChangeModal">
            <div class="relative top-20 mx-auto p-6 border w-[28rem] shadow-xl rounded-xl bg-white">
                <div class="flex flex-col items-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 mb-4">
                        <i class="fas fa-key text-2xl text-yellow-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Password Change Required</h3>
                    <div class="text-center mb-6">
                        <p class="text-gray-600">
                            For security reasons, please change your default password before continuing.
                        </p>
                        <p class="text-sm text-gray-500 mt-2 bg-gray-50 p-3 rounded-lg">
                            Your current password is your date of birth (YYYYMMDD)
                        </p>
                    </div>
                    <div class="w-full">
                        <a href="{{ route('password.change') }}" 
                           class="w-full inline-flex items-center justify-center px-6 py-3 bg-primary-600 text-white text-base font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Set New Password
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('passwordChangeModal');
                modal.classList.add('backdrop-blur-sm');
                modal.style.display = 'block';
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('passwordChangeModal').style.display = 'block';
            });
        </script>
    @endif
@endsection
