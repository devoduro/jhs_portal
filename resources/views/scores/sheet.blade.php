<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $subject->title }} — {{ $classGroup->name }}</h2>
                <p class="text-sm text-gray-500">{{ $term->full_name }}</p>
            </div>
            <x-button href="{{ route('scores.index', ['class_group_id' => $classGroup->id, 'semester_id' => $term->id]) }}" variant="secondary" icon="fas fa-arrow-left">All subjects</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm">
                Class score is out of <strong>{{ rtrim(rtrim(number_format($classMax, 2), '0'), '.') }}</strong> and exam score out of <strong>{{ rtrim(rtrim(number_format($examMax, 2), '0'), '.') }}</strong>.
                The total, grade and positions are calculated for you. Leave both boxes empty for a pupil who did not take the subject.
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
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Class Score ({{ rtrim(rtrim(number_format($classMax, 2), '0'), '.') }})</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Exam Score ({{ rtrim(rtrim(number_format($examMax, 2), '0'), '.') }})</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Position</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($students as $student)
                                @php $r = $results->get($student->id); @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ $student->full_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $student->index_number }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <input type="number" step="0.5" min="0" max="{{ $classMax }}" name="scores[{{ $student->id }}][class_score]"
                                               value="{{ old("scores.{$student->id}.class_score", $r?->class_score !== null ? (float) $r->class_score : '') }}"
                                               class="score-input class-input w-24 rounded border-gray-300 text-center" data-col="0">
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <input type="number" step="0.5" min="0" max="{{ $examMax }}" name="scores[{{ $student->id }}][exam_score]"
                                               value="{{ old("scores.{$student->id}.exam_score", $r?->exam_score !== null ? (float) $r->exam_score : '') }}"
                                               class="score-input exam-input w-24 rounded border-gray-300 text-center" data-col="1">
                                    </td>
                                    <td class="px-3 py-2 text-center font-semibold total-cell">{{ $r ? (float) $r->score : '' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $r?->grade }} <span class="text-xs text-gray-400">{{ $r?->remark }}</span></td>
                                    <td class="px-3 py-2 text-center">{{ \App\Services\TerminalReportService::ordinal($positions[$student->id] ?? null) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">No pupils in this class yet. Add pupils under Pupils or Classes.</td></tr>
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
            rows.forEach(function (row, rowIndex) {
                var inputs = row.querySelectorAll('.score-input');
                var totalCell = row.querySelector('.total-cell');
                inputs.forEach(function (input) {
                    input.addEventListener('input', function () {
                        var c = row.querySelector('.class-input').value, e = row.querySelector('.exam-input').value;
                        totalCell.textContent = (c === '' && e === '') ? '' : ((parseFloat(c) || 0) + (parseFloat(e) || 0)).toString();
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
