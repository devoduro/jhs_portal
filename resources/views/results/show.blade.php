<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $result->subject->title ?? 'Score' }} — {{ $result->student->full_name ?? 'Pupil' }}
            </h2>
            <div class="flex flex-wrap gap-2">
                @canany(['enter-scores', 'manage-results'])
                    <x-button href="{{ route('results.edit', $result) }}" icon="fas fa-pen-to-square">Edit on score sheet</x-button>
                @endcanany
                <x-button href="{{ route('results.index', ['semester_id' => $result->semester_id, 'class_group_id' => $classGroup?->id]) }}" variant="secondary" icon="fas fa-arrow-left">All Results</x-button>
            </div>
        </div>
    </x-slot>

    @php $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 1), '0'), '.'); @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Pupil -->
            <div class="rounded-lg bg-white p-6 text-center shadow-sm">
                @if($result->student)
                    <x-student-photo :student="$result->student" class="mx-auto h-24 w-24 rounded-full object-cover" />
                    <p class="mt-3 font-semibold text-gray-900">{{ $result->student->full_name }}</p>
                    <p class="text-sm text-gray-500">{{ $result->student->index_number }}</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $classGroup->name ?? \App\Support\FormLabel::label($result->student->level) }}</p>
                    <div class="mt-4 flex flex-col gap-2 text-sm">
                        <a href="{{ route('students.report-card', ['student' => $result->student, 'semester_id' => $result->semester_id]) }}" target="_blank" class="text-primary-600 hover:underline"><i class="fas fa-file-lines mr-1"></i> {{ $result->semester->name ?? '' }} terminal report</a>
                        @can('manage-students')
                            <a href="{{ route('students.show', $result->student) }}" class="text-primary-600 hover:underline"><i class="fas fa-user mr-1"></i> Pupil profile</a>
                        @endcan
                    </div>
                @else
                    <p class="text-gray-500">This pupil's record no longer exists.</p>
                @endif
            </div>

            <!-- Score -->
            <div class="rounded-lg bg-white shadow-sm md:col-span-2">
                <div class="border-b px-6 py-4">
                    <p class="text-sm text-gray-500">{{ $result->semester->full_name ?? ($result->academicYear->name ?? '') }}</p>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $result->subject->title ?? '—' }} <span class="text-sm font-normal text-gray-400">{{ $result->subject->code ?? '' }}</span></h3>
                </div>

                <div class="grid grid-cols-3 divide-x border-b text-center">
                    <div class="p-5">
                        <p class="text-xs uppercase text-gray-500">Class score</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ $fmt($result->class_score) }}</p>
                        <p class="text-xs text-gray-400">out of {{ $fmt(\App\Services\TerminalReportService::classScoreMax()) }}</p>
                    </div>
                    <div class="p-5">
                        <p class="text-xs uppercase text-gray-500">Exam score</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ $fmt($result->exam_score) }}</p>
                        <p class="text-xs text-gray-400">out of {{ $fmt(\App\Services\TerminalReportService::examScoreMax()) }}</p>
                    </div>
                    <div class="bg-primary-50 p-5">
                        <p class="text-xs uppercase text-primary-700">Total</p>
                        <p class="mt-1 text-2xl font-bold text-primary-700">{{ $fmt($result->score) }}</p>
                        <p class="text-xs text-primary-600">out of 100</p>
                    </div>
                </div>

                <dl class="grid grid-cols-1 gap-4 p-6 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-gray-500">Grade</dt>
                        <dd class="mt-1"><span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800">{{ $result->grade }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Remark</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $result->remark ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Position in subject</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $position ? \App\Services\TerminalReportService::ordinal($position) . ' out of ' . $classSize : '—' }}</dd>
                    </div>
                </dl>

                <p class="border-t px-6 py-3 text-xs text-gray-400">Last updated {{ $result->updated_at?->format('j M Y, g:i A') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
