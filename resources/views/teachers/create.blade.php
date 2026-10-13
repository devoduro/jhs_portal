<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add a Teacher') }}</h2>
            <div class="flex gap-2">
                <x-button href="{{ route('teachers.import.form') }}" variant="secondary" icon="fas fa-file-excel">Import from Excel</x-button>
                <x-button href="{{ route('teachers.index') }}" variant="secondary" icon="fas fa-arrow-left">Teachers</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('teachers.store') }}" enctype="multipart/form-data">
                @csrf
                @include('teachers._form', ['teacher' => null])
                <div class="sticky bottom-0 -mx-4 mt-6 flex items-center justify-between gap-3 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:shadow-sm">
                    <p class="hidden text-xs text-gray-500 sm:block"><span class="text-red-500">*</span> Only the name is required. Subjects and classes can be assigned later.</p>
                    <div class="flex gap-3">
                        <x-button type="submit" name="after" value="another" variant="secondary" icon="fas fa-plus">Save & add another</x-button>
                        <x-button type="submit" variant="success" icon="fas fa-check">Add Teacher</x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
