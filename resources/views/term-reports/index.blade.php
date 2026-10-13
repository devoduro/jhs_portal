<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Attendance, Conduct & Remarks') }}</h2>
            @if($classGroup && $term)
                <div class="flex gap-2">
                    <x-button href="{{ route('scores.index', ['class_group_id' => $classGroup->id, 'semester_id' => $term->id]) }}" variant="secondary" icon="fas fa-pen">Scores</x-button>
                    <x-button href="{{ route('report-cards.bulk-print', ['class_group_id' => $classGroup->id, 'semester_id' => $term->id]) }}" icon="fas fa-print" target="_blank">Print Reports</x-button>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[1500px] mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('term-reports.index') }}" class="bg-white shadow-sm rounded-lg p-6 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                    <select name="semester_id" class="rounded-lg border-gray-300 text-sm">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_group_id" class="rounded-lg border-gray-300 text-sm" required>
                        <option value="">Choose a class...</option>
                        @foreach($classGroups as $c)
                            <option value="{{ $c->id }}" @selected($classGroup && $classGroup->id === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" icon="fas fa-arrow-right">Open</x-button>
            </form>

            @if($classGroups->isEmpty())
                <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 text-sm">
                    You are not the class teacher of any class and are not assigned to teach any subject yet. The administrator sets class teachers under Classes and subject teachers under Subjects.
                </div>
            @endif

            @if(request()->filled('class_group_id') && !$classGroup && $classGroups->isNotEmpty())
                <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 text-sm">
                    You are not assigned to that class, so it is not in your list. Choose one of your classes above.
                </div>
            @endif

            @if($data)
                <datalist id="conduct-options">@foreach(\App\Models\TermReport::CONDUCT_OPTIONS as $o)<option value="{{ $o }}">@endforeach</datalist>
                <datalist id="attitude-options">@foreach(\App\Models\TermReport::ATTITUDE_OPTIONS as $o)<option value="{{ $o }}">@endforeach</datalist>
                <datalist id="interest-options">@foreach(\App\Models\TermReport::INTEREST_OPTIONS as $o)<option value="{{ $o }}">@endforeach</datalist>
                <datalist id="teacher-remarks">@foreach(\App\Models\TermReport::TEACHER_REMARKS as $o)<option value="{{ $o }}">@endforeach</datalist>
                <datalist id="head-remarks">@foreach(\App\Models\TermReport::HEAD_REMARKS as $o)<option value="{{ $o }}">@endforeach</datalist>

                <form method="POST" action="{{ route('term-reports.save', $classGroup) }}" class="bg-white shadow-sm rounded-lg overflow-hidden">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $term->id }}">

                    <div class="px-6 py-4 border-b flex flex-wrap justify-between items-end gap-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $classGroup->name }} — {{ $term->full_name }}</h3>
                            <p class="text-sm text-gray-500">{{ $data['roll'] }} pupils. Remarks lists are suggestions — you can type anything.</p>
                        </div>
                        <div class="flex items-end gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">School days this term</label>
                                <input type="number" name="attendance_days" min="0" max="200" value="{{ $term->attendance_days }}" class="w-28 rounded-lg border-gray-300 text-sm">
                            </div>
                            <button type="button" id="autofill" class="px-3 py-2 text-sm rounded-lg border border-primary-500 text-primary-700 hover:bg-primary-50">
                                <i class="fas fa-magic mr-1"></i> Fill blank remarks from performance
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pupil</th>
                                    <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase">Avg / Pos</th>
                                    <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase">Days present</th>
                                    <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conduct</th>
                                    <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attitude</th>
                                    <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Interest</th>
                                    <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class teacher's remark</th>
                                    @if($canWriteHeadRemark)
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Head teacher's remark</th>
                                    @endif
                                    @if($isFinalTerm)
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Promoted to</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($data['pupils'] as $pupil)
                                    @php
                                        $s = $pupil['student'];
                                        $tr = $pupil['term_report'];
                                        $nextLevel = \App\Support\FormLabel::next($classGroup->level);
                                        $promoted = $tr?->promoted_to ?? ($pupil['average'] !== null ? ($nextLevel ?? 0) : null);
                                    @endphp
                                    <tr class="align-top" data-average="{{ $pupil['average'] }}">
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">{{ $s->full_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $s->index_number }}</div>
                                        </td>
                                        <td class="px-2 py-2 text-center whitespace-nowrap">
                                            {{ $pupil['average'] ?? '—' }}<br>
                                            <span class="text-xs text-gray-500">{{ \App\Services\TerminalReportService::ordinal($pupil['position']) }}</span>
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <input type="number" min="0" max="200" name="reports[{{ $s->id }}][days_present]" value="{{ $tr?->days_present }}" class="w-20 rounded border-gray-300 text-sm text-center">
                                        </td>
                                        <td class="px-2 py-2"><input type="text" list="conduct-options" name="reports[{{ $s->id }}][conduct]" value="{{ $tr?->conduct }}" class="w-36 rounded border-gray-300 text-sm"></td>
                                        <td class="px-2 py-2"><input type="text" list="attitude-options" name="reports[{{ $s->id }}][attitude]" value="{{ $tr?->attitude }}" class="w-40 rounded border-gray-300 text-sm"></td>
                                        <td class="px-2 py-2"><input type="text" list="interest-options" name="reports[{{ $s->id }}][interest]" value="{{ $tr?->interest }}" class="w-36 rounded border-gray-300 text-sm"></td>
                                        <td class="px-2 py-2"><input type="text" list="teacher-remarks" name="reports[{{ $s->id }}][class_teacher_remark]" value="{{ $tr?->class_teacher_remark }}" class="teacher-remark w-72 rounded border-gray-300 text-sm"></td>
                                        @if($canWriteHeadRemark)
                                            <td class="px-2 py-2"><input type="text" list="head-remarks" name="reports[{{ $s->id }}][head_teacher_remark]" value="{{ $tr?->head_teacher_remark }}" class="head-remark w-64 rounded border-gray-300 text-sm"></td>
                                        @endif
                                        @if($isFinalTerm)
                                            <td class="px-2 py-2">
                                                <select name="reports[{{ $s->id }}][promoted_to]" class="rounded border-gray-300 text-sm">
                                                    <option value="">—</option>
                                                    @foreach(\App\Support\FormLabel::options() as $lvl => $label)
                                                        @continue($lvl < $classGroup->level)
                                                        <option value="{{ $lvl }}" @selected($promoted === $lvl)>{{ $lvl === $classGroup->level ? 'Repeat ' . $label : $label }}</option>
                                                    @endforeach
                                                    @if($nextLevel === null)
                                                        <option value="0" @selected($promoted === 0)>Completed (leaves school)</option>
                                                    @endif
                                                </select>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t flex justify-end">
                        <x-button type="submit" icon="fas fa-save">Save</x-button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var btn = document.getElementById('autofill');
            if (!btn) return;
            var teacher = @json(\App\Models\TermReport::TEACHER_REMARKS);
            var head = @json(\App\Models\TermReport::HEAD_REMARKS);
            btn.addEventListener('click', function () {
                document.querySelectorAll('tr[data-average]').forEach(function (row) {
                    var avg = parseFloat(row.dataset.average);
                    if (isNaN(avg)) return;
                    var band = avg >= 80 ? 0 : avg >= 70 ? 1 : avg >= 60 ? 2 : avg >= 50 ? 3 : 4;
                    var t = row.querySelector('.teacher-remark'), h = row.querySelector('.head-remark');
                    if (t && !t.value) t.value = teacher[band];
                    if (h && !h.value) h.value = head[Math.min(band, head.length - 1)];
                });
            });
        })();
    </script>
</x-app-layout>
