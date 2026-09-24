<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Quiz') }}</h2>
            <x-button href="{{ route('quizzes.show', $quiz) }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Back to Quiz') }}</x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('quizzes.update', $quiz) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" value="{{ old('title', $quiz->title) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="2" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">{{ old('description', $quiz->description) }}</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instructions for Students</label>
                            <textarea name="instructions" rows="2" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">{{ old('instructions', $quiz->instructions) }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                                <select name="subject_id" id="subject_id" required class="block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}" data-level="{{ $subject->level }}"
                                                {{ old('subject_id', $quiz->subject_id) == $subject->id ? 'selected' : '' }}>
                                            {{ $subject->code }} - {{ $subject->title }} ({{ \App\Support\FormLabel::label($subject->level) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Limit to a Class (optional)</label>
                                <select name="class_group_id" id="class_group_id" class="block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                                    <option value="">All classes at this form</option>
                                    @foreach($classGroups as $group)
                                        <option value="{{ $group->id }}" data-level="{{ $group->level }}" {{ old('class_group_id', $quiz->class_group_id) == $group->id ? 'selected' : '' }}>
                                            {{ $group->name }} ({{ \App\Support\FormLabel::label($group->level) }} {{ $group->programme->name ?? '' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes) <span class="text-red-500">*</span></label>
                                <input type="number" name="duration_minutes" min="1" max="300" value="{{ old('duration_minutes', $quiz->duration_minutes) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pass Percentage <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="pass_percentage" min="0" max="100" value="{{ old('pass_percentage', $quiz->pass_percentage) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                            </div>
                            <div class="flex items-end gap-2 pb-2">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="shuffle_questions" value="1" {{ old('shuffle_questions', $quiz->shuffle_questions) ? 'checked' : '' }} class="rounded border-gray-300">
                                    Shuffle question order per student
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="has_negative_marking" id="has_negative_marking" value="1" {{ old('has_negative_marking', $quiz->has_negative_marking) ? 'checked' : '' }} class="rounded border-gray-300">
                                    Deduct marks for wrong answers
                                </label>
                                <input type="number" step="0.01" min="0" name="negative_mark_value" id="negative_mark_value"
                                       value="{{ old('negative_mark_value', $quiz->negative_mark_value) }}" placeholder="Marks to deduct per wrong answer"
                                       class="mt-2 block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md {{ old('has_negative_marking', $quiz->has_negative_marking) ? '' : 'hidden' }}">
                            </div>
                            <div class="space-y-2">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="allow_retake" value="1" {{ old('allow_retake', $quiz->allow_retake) ? 'checked' : '' }} class="rounded border-gray-300">
                                    Allow students to retake this quiz
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 block">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $quiz->is_active) ? 'checked' : '' }} class="rounded border-gray-300">
                                    Active (visible to students)
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Opens At (optional)</label>
                                <input type="datetime-local" name="start_at" value="{{ old('start_at', optional($quiz->start_at)->format('Y-m-d\TH:i')) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Closes At (optional)</label>
                                <input type="datetime-local" name="end_at" value="{{ old('end_at', optional($quiz->end_at)->format('Y-m-d\TH:i')) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-2 border-t border-gray-200">
                            <x-button type="button" variant="secondary" onclick="window.history.back()">{{ __('Cancel') }}</x-button>
                            <x-button type="submit" icon="fas fa-save">{{ __('Save Changes') }}</x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('has_negative_marking').addEventListener('change', function () {
            document.getElementById('negative_mark_value').classList.toggle('hidden', !this.checked);
        });

        function filterClassGroupsBySubject() {
            const subjectSelect = document.getElementById('subject_id');
            const classSelect = document.getElementById('class_group_id');
            const selected = subjectSelect.options[subjectSelect.selectedIndex];
            const level = selected ? selected.dataset.level : null;

            Array.from(classSelect.options).forEach(option => {
                if (!option.value) { return; }
                option.hidden = level && option.dataset.level !== level;
            });
        }

        document.getElementById('subject_id').addEventListener('change', filterClassGroupsBySubject);
        filterClassGroupsBySubject();
    </script>
</x-app-layout>
