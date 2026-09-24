<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Assignment') }}</h2>
            <x-button href="{{ route('assignments.show', $assignment) }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Back to Assignment') }}</x-button>
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

                    <form method="POST" action="{{ route('assignments.update', $assignment) }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" value="{{ old('title', $assignment->title) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description / Instructions</label>
                            <textarea name="description" rows="5" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">{{ old('description', $assignment->description) }}</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Attachment</label>
                            @if($assignment->attachment_path)
                                <p class="text-xs text-gray-500 mb-1">Current: {{ $assignment->attachment_name }} - upload a new file to replace it.</p>
                            @endif
                            <input type="file" name="attachment" class="block w-full text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                            <select name="subject_id" id="subject_id" required class="block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}" data-level="{{ $subject->level }}" {{ old('subject_id', $assignment->subject_id) == $subject->id ? 'selected' : '' }}>
                                        {{ $subject->code }} - {{ $subject->title }} ({{ \App\Support\FormLabel::label($subject->level) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Classes <span class="text-red-500">*</span></label>
                            <div id="class-group-list" class="grid grid-cols-1 md:grid-cols-2 gap-2 border border-gray-200 rounded-md p-3 max-h-56 overflow-y-auto">
                                @foreach($classGroups as $group)
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 class-group-option" data-level="{{ $group->level }}">
                                        <input type="checkbox" name="class_group_ids[]" value="{{ $group->id }}" {{ in_array($group->id, old('class_group_ids', $selectedClassGroupIds)) ? 'checked' : '' }} class="rounded border-gray-300">
                                        {{ $group->name }} ({{ \App\Support\FormLabel::label($group->level) }} {{ $group->programme->name ?? '' }})
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Due Date &amp; Time <span class="text-red-500">*</span></label>
                                <input type="datetime-local" name="due_at" value="{{ old('due_at', $assignment->due_at->format('Y-m-d\TH:i')) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Score (optional)</label>
                                <input type="number" step="0.5" min="0" name="max_score" value="{{ old('max_score', $assignment->max_score) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="allow_late_submission" value="1" {{ old('allow_late_submission', $assignment->allow_late_submission) ? 'checked' : '' }} class="rounded border-gray-300">
                                Allow submissions after the deadline (marked as late)
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 block">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $assignment->is_active) ? 'checked' : '' }} class="rounded border-gray-300">
                                Active (visible to students)
                            </label>
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
        function filterClassGroupsBySubject() {
            const subjectSelect = document.getElementById('subject_id');
            const selected = subjectSelect.options[subjectSelect.selectedIndex];
            const level = selected ? selected.dataset.level : null;

            document.querySelectorAll('.class-group-option').forEach(option => {
                const matches = !level || option.dataset.level === level;
                option.style.display = matches ? '' : 'none';
            });
        }

        document.getElementById('subject_id').addEventListener('change', filterClassGroupsBySubject);
        filterClassGroupsBySubject();
    </script>
</x-app-layout>
