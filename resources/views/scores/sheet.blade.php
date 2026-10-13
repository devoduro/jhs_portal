<x-app-layout>
    @php
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        $configured = $columns->contains(fn ($c) => $c['id'] !== null);
        $classCols = $columns->where('type', 'class');
        $examCols = $columns->where('type', 'exam');
        $sheetTotal = $columns->sum('max');
    @endphp
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $subject->title }} — {{ $classGroup->name }}</h2>
                <p class="text-sm text-gray-500">{{ $term->full_name }}</p>
            </div>
            <div class="flex gap-2">
                @can('manage-settings')
                    <x-button href="{{ route('assessment-settings.edit', $classGroup->level) }}" variant="secondary" icon="fas fa-sliders">Change score sheet</x-button>
                @endcan
                <x-button href="{{ route('scores.index', ['class_group_id' => $classGroup->id, 'semester_id' => $term->id]) }}" variant="secondary" icon="fas fa-arrow-left">All subjects</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm">
                This sheet is marked out of <strong>{{ $fmt($sheetTotal) }}</strong>:
                @foreach($columns as $column)
                    {{ $column['label'] }} <strong>{{ $fmt($column['max']) }}</strong>{{ $loop->last ? '.' : ',' }}
                @endforeach
                The total, grade and positions are calculated for you. Leave a pupil's boxes empty if they did not take the subject.
                Press <kbd class="px-1 bg-white border rounded">Enter</kbd> to move down.
            </div>

            @if($errors->any())
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('scores.save', ['classGroup' => $classGroup, 'subject' => $subject]) }}" class="bg-white shadow-sm rounded-lg overflow-hidden">
                @csrf
                <input type="hidden" name="semester_id" value="{{ $term->id }}">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200" id="score-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-10">#</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pupil</th>
                                @foreach($columns as $column)
                                    <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                        {{ $column['label'] }}
                                        <span class="block normal-case text-gray-400">({{ $fmt($column['max']) }})</span>
                                    </th>
                                @endforeach
                                @if($configured)
                                    <th class="px-2 py-3 text-center text-xs font-medium text-indigo-500 uppercase">Class<span class="block normal-case text-gray-400">({{ $fmt($classCols->sum('max')) }})</span></th>
                                    <th class="px-2 py-3 text-center text-xs font-medium text-indigo-500 uppercase">Exam<span class="block normal-case text-gray-400">({{ $fmt($examCols->sum('max')) }})</span></th>
                                @endif
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Position</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($students as $student)
                                @php
                                    $r = $results->get($student->id);
                                    $stored = $r ? ($componentScores->get($r->id) ?? collect()) : collect();
                                    $legacy = $configured && $r && $stored->isEmpty();
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ $student->full_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $student->index_number }}</div>
                                        @if($legacy)
                                            <div class="text-xs text-orange-600" title="Scored before this class had its own score sheet. Entering marks here replaces that total.">Earlier total kept</div>
                                        @endif
                                    </td>
                                    @foreach($columns as $column)
                                        @php
                                            if ($column['id'] === null) {
                                                $existing = $column['key'] === 'class_score' ? $r?->class_score : $r?->exam_score;
                                            } else {
                                                $existing = $stored->get($column['id']);
                                            }
                                        @endphp
                                        <td class="px-2 py-2 text-center">
                                            <input type="number" step="0.5" min="0" max="{{ $column['max'] }}" name="scores[{{ $student->id }}][{{ $column['key'] }}]"
                                                   value="{{ old("scores.{$student->id}.{$column['key']}", $existing !== null ? (float) $existing : '') }}"
                                                   data-col="{{ $loop->index }}" data-type="{{ $column['type'] }}"
                                                   class="score-input w-20 rounded border-gray-300 text-center">
                                        </td>
                                    @endforeach
                                    @if($configured)
                                        <td class="px-2 py-2 text-center text-indigo-700 class-sub">{{ $r && $r->class_score !== null ? (float) $r->class_score : '' }}</td>
                                        <td class="px-2 py-2 text-center text-indigo-700 exam-sub">{{ $r && $r->exam_score !== null ? (float) $r->exam_score : '' }}</td>
                                    @endif
                                    <td class="px-3 py-2 text-center font-semibold total-cell">{{ $r ? (float) $r->score : '' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $r?->grade }} <span class="text-xs text-gray-400">{{ $r?->remark }}</span></td>
                                    <td class="px-3 py-2 text-center">{{ \App\Services\TerminalReportService::ordinal($positions[$student->id] ?? null) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ 5 + $columns->count() + ($configured ? 2 : 0) }}" class="px-3 py-8 text-center text-gray-400">No pupils in this class yet. Add pupils under Pupils or Classes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($students->isNotEmpty())
                    <div class="p-4 flex justify-between items-center border-t">
                        <p class="text-xs text-gray-500">Grades and positions refresh after saving.</p>
                        <x-button type="submit" icon="fas fa-save">Save Scores</x-button>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <script>
        (function () {
            var rows = Array.prototype.slice.call(document.querySelectorAll('#score-table tbody tr'));
            var sum = function (inputs) {
                var any = false, total = 0;
                inputs.forEach(function (i) { if (i.value !== '') { any = true; total += parseFloat(i.value) || 0; } });
                return any ? Math.round(total * 100) / 100 : '';
            };
            rows.forEach(function (row, rowIndex) {
                var inputs = Array.prototype.slice.call(row.querySelectorAll('.score-input'));
                if (!inputs.length) return;
                var totalCell = row.querySelector('.total-cell');
                var classSub = row.querySelector('.class-sub'), examSub = row.querySelector('.exam-sub');
                inputs.forEach(function (input) {
                    input.addEventListener('input', function () {
                        var classSum = sum(inputs.filter(function (i) { return i.dataset.type === 'class'; }));
                        var examSum = sum(inputs.filter(function (i) { return i.dataset.type === 'exam'; }));
                        if (classSub) classSub.textContent = classSum;
                        if (examSub) examSub.textContent = examSum;
                        totalCell.textContent = (classSum === '' && examSum === '') ? '' : (classSum || 0) + (examSum || 0);
                        input.classList.toggle('border-red-500', input.value !== '' && parseFloat(input.value) > parseFloat(input.max));
                    });
                    // Enter moves down the same column (like a spreadsheet) instead of submitting.
                    input.addEventListener('keydown', function (ev) {
                        if (ev.key !== 'Enter') return;
                        ev.preventDefault();
                        var next = rows[rowIndex + 1];
                        var target = next ? next.querySelectorAll('.score-input')[input.dataset.col] : null;
                        if (target) { target.focus(); target.select(); }
                    });
                });
            });
        })();
    </script>
</x-app-layout>
