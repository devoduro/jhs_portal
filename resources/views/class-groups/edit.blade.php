<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ isset($classGroup) ? 'Edit Class' : 'Add Class' }}</h2>
            <x-button href="{{ route('class-groups.index') }}" variant="secondary" icon="fas fa-arrow-left">Classes</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ isset($classGroup) ? route('class-groups.update', $classGroup) : route('class-groups.store') }}" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                @csrf
                @isset($classGroup) @method('PUT') @endisset

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="level" class="block text-sm font-medium text-gray-700">Class level <span class="text-red-500">*</span></label>
                        <select id="level" name="level" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="">Select...</option>
                            @foreach(\App\Support\FormLabel::options() as $levelOption => $levelLabel)
                                <option value="{{ $levelOption }}" @selected(old('level', $classGroup->level ?? '') == $levelOption)>{{ $levelLabel }}</option>
                            @endforeach
                        </select>
                        @error('level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <x-input id="name" name="name" label="Class name" :value="old('name', $classGroup->name ?? '')" required
                             placeholder="e.g. Basic 4A, JHS 2 Gold" helper="Use the level plus a stream letter or name if you have more than one class per level." />

                    <div class="md:col-span-2">
                        <label for="class_teacher_id" class="block text-sm font-medium text-gray-700">Class teacher</label>
                        <select id="class_teacher_id" name="class_teacher_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Not assigned</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected(old('class_teacher_id', $classGroup->class_teacher_id ?? '') == $teacher->id)>{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-sm text-gray-500">The class teacher can enter scores for every subject in this class, and attendance and remarks for the terminal report.</p>
                        @error('class_teacher_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-button href="{{ route('class-groups.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" icon="fas fa-check">{{ isset($classGroup) ? 'Save Changes' : 'Create Class' }}</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
