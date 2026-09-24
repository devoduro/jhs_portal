<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Add Subject') }}
            </h2>
            <x-button href="{{ route('subjects.index') }}" variant="secondary" icon="fas fa-arrow-left">
                {{ __('Back to Subjects') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-700 text-sm">
                        Most subjects run continuously across every form a programme offers, so fill this in once and tick every Form it should apply to - one subject row is created per Form you select, all sharing the same programmes, teachers and core/elective setting. If a subject only starts partway through (e.g. Form 2 only), just tick that Form alone.
                    </div>

                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('subjects.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div class="space-y-6">
                                <!-- Subject Code Prefix -->
                                <div>
                                    <x-input
                                        id="code_prefix"
                                        name="code_prefix"
                                        type="text"
                                        label="Subject Code Prefix"
                                        :value="old('code_prefix')"
                                        required
                                        autofocus
                                        placeholder="e.g. ENG"
                                    />
                                    <p class="text-xs text-gray-500 mt-1" id="code-preview">
                                        Just the letters - the form number is added automatically (e.g. "ENG" becomes ENG101 for Form 1, ENG201 for Form 2).
                                    </p>
                                </div>

                                <!-- Subject Name -->
                                <div>
                                    <x-input
                                        id="title"
                                        name="title"
                                        type="text"
                                        label="Subject Title"
                                        :value="old('title')"
                                        required
                                        placeholder="e.g. English Language"
                                    />
                                </div>

                                <!-- Credits -->
                                <div>
                                    <x-input
                                        id="credit_hours"
                                        name="credit_hours"
                                        type="number"
                                        label="Credit Hours"
                                        :value="old('credit_hours')"
                                        required
                                        min="1"
                                        max="6"
                                        step="1"
                                    />
                                </div>

                                <!-- Forms -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Forms <span class="text-red-500">*</span></label>
                                    <div class="flex gap-4">
                                        @foreach(\App\Support\FormLabel::OPTIONS as $levelOption => $levelLabel)
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" name="levels[]" value="{{ $levelOption }}" class="level-checkbox rounded border-gray-300"
                                                       {{ in_array($levelOption, old('levels', [])) ? 'checked' : '' }}>
                                                {{ $levelLabel }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('levels')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="space-y-6">
                                <!-- Semester -->
                                <div>
                                    <label for="semester_id" class="block text-sm font-medium text-gray-700">Semester</label>
                                    <select id="semester_id" name="semester_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                                        <option value="">Select Semester</option>
                                        @foreach($semesters as $semester)
                                            <option value="{{ $semester->id }}" {{ old('semester_id') == $semester->id ? 'selected' : '' }}>
                                                {{ $semester->name }} ({{ $semester->academicYear->name ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('semester_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Programmes -->
                                <div>
                                    <label for="programmes" class="block text-sm font-medium text-gray-700">Programmes</label>
                                    <p class="text-xs text-gray-500 mb-1">Select every programme that takes this subject - tick more than one for a subject that "runs through" several programmes.</p>
                                    <select id="programmes" name="programme_ids[]" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md" multiple required size="5">
                                        @foreach($programmes as $programme)
                                            <option value="{{ $programme->id }}"
                                                {{ in_array($programme->id, old('programme_ids', [])) ? 'selected' : '' }}>
                                                {{ $programme->name }} ({{ $programme->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd key to select multiple programmes</p>
                                    @error('programme_ids')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    @error('programme_ids.*')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Teachers -->
                                <div>
                                    <label for="teachers" class="block text-sm font-medium text-gray-700">Teachers</label>
                                    <p class="text-xs text-gray-500 mb-1">Select all teachers who teach this subject. Hold Ctrl/Cmd to select multiple, or leave empty if not yet assigned.</p>
                                    <select id="teachers" name="teacher_ids[]" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md" multiple size="5">
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}"
                                                {{ in_array($teacher->id, old('teacher_ids', [])) ? 'selected' : '' }}>
                                                {{ $teacher->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('teacher_ids')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    @error('teacher_ids.*')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Subject Type -->
                                <div>
                                    <label for="is_core" class="block text-sm font-medium text-gray-700">Subject Type</label>
                                    <select id="is_core" name="is_core" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                                        <option value="1" {{ old('is_core', '1') == 1 ? 'selected' : '' }}>Core</option>
                                        <option value="0" {{ old('is_core') == 0 ? 'selected' : '' }}>Elective</option>
                                    </select>
                                    @error('is_core')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mt-6">
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <x-button type="button" variant="secondary" onclick="window.history.back()">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit">
                                {{ __('Create Subject') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateCodePreview() {
            const prefix = document.getElementById('code_prefix').value.trim().toUpperCase().replace(/[^A-Z]/g, '');
            const checked = [...document.querySelectorAll('.level-checkbox:checked')].map(cb => cb.value);
            const preview = document.getElementById('code-preview');

            if (!prefix || checked.length === 0) {
                preview.textContent = 'Just the letters - the form number is added automatically (e.g. "ENG" becomes ENG101 for Form 1, ENG201 for Form 2).';
                return;
            }

            const codes = checked.map(level => prefix + Math.floor(level / 100) + '01');
            preview.textContent = 'Will create: ' + codes.join(', ');
        }

        document.getElementById('code_prefix').addEventListener('input', updateCodePreview);
        document.querySelectorAll('.level-checkbox').forEach(cb => cb.addEventListener('change', updateCodePreview));
    </script>
</x-app-layout>
