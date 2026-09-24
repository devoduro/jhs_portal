<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add Subject') }}</h2>
            <x-button href="{{ route('subjects.index') }}" variant="secondary" icon="fas fa-arrow-left">Subjects</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('subjects.store') }}" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                @csrf
                @if($errors->any())
                    <div class="p-3 bg-red-50 text-red-700 text-sm rounded">{{ $errors->first() }}</div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <x-input name="title" label="Subject name" :value="old('title')" required placeholder="e.g. Computing" class="md:col-span-2" />
                    <x-input name="code_prefix" label="Short code" :value="old('code_prefix')" required placeholder="e.g. COMP" helper="Letters only. The class is added: COMP-B4, COMP-J1..." />
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">Taught in <span class="text-red-500">*</span></label>
                        <div class="text-xs space-x-3">
                            @foreach(\App\Support\FormLabel::SECTIONS as $key => $label)
                                <button type="button" class="text-primary-600 hover:underline" onclick="document.querySelectorAll('.lvl[data-section={{ $key }}]').forEach(b => b.checked = true)">+ {{ strtok($label, ' ') }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                        @foreach(\App\Support\FormLabel::options() as $level => $label)
                            <label class="flex items-center gap-2 p-2 border rounded-lg text-sm">
                                <input type="checkbox" name="levels[]" value="{{ $level }}" class="lvl rounded border-gray-300" data-section="{{ \App\Support\FormLabel::section($level) }}" @checked(in_array($level, old('levels', [])))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_core" value="0">
                    <input type="checkbox" name="is_core" value="1" class="rounded border-gray-300" @checked(old('is_core'))>
                    Core subject (always counted in the JHS aggregate)
                </label>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject teacher(s)</label>
                    <select name="teacher_ids[]" multiple size="5" class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected(in_array($teacher->id, old('teacher_ids', [])))>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Optional. In primary the class teacher usually teaches everything and doesn't need to be listed here. Hold Ctrl to pick several.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded-lg border-gray-300 text-sm">{{ old('description') }}</textarea>
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" icon="fas fa-check">Add Subject</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
