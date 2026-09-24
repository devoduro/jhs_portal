<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Subject') }} — {{ $subject->title }} ({{ \App\Support\FormLabel::label($subject->level) }})</h2>
            <x-button href="{{ route('subjects.index', ['level' => $subject->level]) }}" variant="secondary" icon="fas fa-arrow-left">Subjects</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('subjects.update', $subject) }}" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                @csrf
                @method('PUT')
                @if($errors->any())
                    <div class="p-3 bg-red-50 text-red-700 text-sm rounded">{{ $errors->first() }}</div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <x-input name="title" label="Subject name" :value="old('title', $subject->title)" required class="md:col-span-2" />
                    <x-input name="code" label="Code" :value="old('code', $subject->code)" required />
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Class level</label>
                        <select name="level" class="w-full rounded-lg border-gray-300 text-sm" required>
                            @foreach(\App\Support\FormLabel::options() as $level => $label)
                                <option value="{{ $level }}" @selected(old('level', $subject->level) == $level)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_core" value="0">
                    <input type="checkbox" name="is_core" value="1" class="rounded border-gray-300" @checked(old('is_core', $subject->is_core))>
                    Core subject (always counted in the JHS aggregate)
                </label>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject teacher(s)</label>
                    <select name="teacher_ids[]" multiple size="5" class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected(in_array($teacher->id, old('teacher_ids', $subject->teachers->pluck('id')->all())))>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded-lg border-gray-300 text-sm">{{ old('description', $subject->description) }}</textarea>
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" icon="fas fa-check">Save Changes</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
