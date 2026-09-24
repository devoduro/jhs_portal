@php
    $existingOptions = $question && $question->type === 'mcq' ? array_values($question->options) : [];
    $existingCorrectIndex = null;
    if ($question && $question->type === 'mcq') {
        $existingCorrectIndex = array_search($question->correct_answer, array_keys($question->options), true);
    }
    $tfCorrect = $question && $question->type === 'true_false' ? $question->correct_answer : null;
@endphp

<form method="POST" action="{{ $action }}" id="quiz-question-form" class="space-y-6">
    @csrf
    @if($question)
        @method('PUT')
    @endif

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Question Text <span class="text-red-500">*</span></label>
        <textarea name="question_text" rows="2" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">{{ old('question_text', $question->question_text ?? '') }}</textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
            <select name="type" id="question_type" class="block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                <option value="mcq" {{ old('type', $question->type ?? 'mcq') === 'mcq' ? 'selected' : '' }}>Multiple Choice</option>
                <option value="true_false" {{ old('type', $question->type ?? '') === 'true_false' ? 'selected' : '' }}>True / False</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Marks <span class="text-red-500">*</span></label>
            <input type="number" step="0.5" min="0.5" name="marks" value="{{ old('marks', $question->marks ?? 1) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
        </div>
    </div>

    <div id="mcq-section">
        <label class="block text-sm font-medium text-gray-700 mb-2">Options - select the correct one <span class="text-red-500">*</span></label>
        <div id="option-rows" class="space-y-2">
            @forelse($existingOptions as $i => $text)
                <div class="flex items-center gap-2 option-row">
                    <input type="radio" name="correct_answer" value="{{ $i }}" {{ $existingCorrectIndex === $i ? 'checked' : '' }} required class="text-primary-600">
                    <input type="text" name="options[]" value="{{ $text }}" placeholder="Option text" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    <button type="button" class="remove-option text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                </div>
            @empty
                <div class="flex items-center gap-2 option-row">
                    <input type="radio" name="correct_answer" value="0" required class="text-primary-600">
                    <input type="text" name="options[]" placeholder="Option text" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    <button type="button" class="remove-option text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                </div>
                <div class="flex items-center gap-2 option-row">
                    <input type="radio" name="correct_answer" value="1" required class="text-primary-600">
                    <input type="text" name="options[]" placeholder="Option text" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    <button type="button" class="remove-option text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                </div>
            @endforelse
        </div>
        <button type="button" id="add-option" class="mt-2 text-sm text-primary-600 hover:underline"><i class="fas fa-plus mr-1"></i> Add Option</button>
    </div>

    <div id="tf-section" class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-2">Correct Answer <span class="text-red-500">*</span></label>
        <div class="flex gap-6">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="radio" name="tf_correct_answer" value="true" {{ $tfCorrect === 'true' ? 'checked' : '' }} class="text-primary-600"> True
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="radio" name="tf_correct_answer" value="false" {{ $tfCorrect === 'false' ? 'checked' : '' }} class="text-primary-600"> False
            </label>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Explanation (shown to students after they submit)</label>
        <textarea name="explanation" rows="2" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">{{ old('explanation', $question->explanation ?? '') }}</textarea>
    </div>

    <div class="flex justify-end space-x-3 pt-2 border-t border-gray-200">
        <x-button type="button" variant="secondary" onclick="window.history.back()">{{ __('Cancel') }}</x-button>
        <x-button type="submit" icon="fas fa-save">{{ __('Save Question') }}</x-button>
    </div>
</form>

<script>
    (function () {
        const typeSelect = document.getElementById('question_type');
        const mcqSection = document.getElementById('mcq-section');
        const tfSection = document.getElementById('tf-section');
        const rowsContainer = document.getElementById('option-rows');
        const addBtn = document.getElementById('add-option');

        function toggleSections() {
            const isMcq = typeSelect.value === 'mcq';
            mcqSection.classList.toggle('hidden', !isMcq);
            tfSection.classList.toggle('hidden', isMcq);

            // Disabled fields are never submitted, unlike merely-hidden ones - without this,
            // switching type left the other section's inputs in the POST body (as empty
            // strings, which Laravel's ConvertEmptyStringsToNull middleware turns into null,
            // failing the "must be a string" rule on the section that should've been ignored).
            mcqSection.querySelectorAll('input').forEach(input => { input.disabled = !isMcq; });
            tfSection.querySelectorAll('input').forEach(input => { input.disabled = isMcq; });
        }

        function renumberRows() {
            rowsContainer.querySelectorAll('.option-row').forEach((row, index) => {
                row.querySelector('input[type="radio"]').value = index;
            });
        }

        addBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 option-row';
            const index = rowsContainer.querySelectorAll('.option-row').length;
            row.innerHTML = `
                <input type="radio" name="correct_answer" value="${index}" required class="text-primary-600">
                <input type="text" name="options[]" placeholder="Option text" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                <button type="button" class="remove-option text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
            `;
            rowsContainer.appendChild(row);
        });

        rowsContainer.addEventListener('click', function (e) {
            const btn = e.target.closest('.remove-option');
            if (!btn) return;
            if (rowsContainer.querySelectorAll('.option-row').length <= 2) {
                alert('A multiple choice question needs at least 2 options.');
                return;
            }
            btn.closest('.option-row').remove();
            renumberRows();
        });

        typeSelect.addEventListener('change', toggleSections);
        toggleSections();

        // The true/false radio uses a different field name (tf_correct_answer) so it doesn't
        // collide with the mcq index-based correct_answer radios; wire it to the real field
        // the server expects right before submit. Scoped to this specific form by id - a bare
        // document.querySelector('form') can silently grab an unrelated form elsewhere on the
        // page (e.g. the header's hidden logout form), which is what happened here originally.
        document.getElementById('quiz-question-form').addEventListener('submit', function () {
            if (typeSelect.value === 'true_false') {
                const checked = document.querySelector('input[name="tf_correct_answer"]:checked');
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'correct_answer';
                hidden.value = checked ? checked.value : '';
                this.appendChild(hidden);
            }
        });
    })();
</script>
