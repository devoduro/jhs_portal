<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Admit a Pupil') }}</h2>
            <div class="flex gap-2">
                <x-button href="{{ route('students.import.form') }}" variant="secondary" icon="fas fa-file-excel">Import from Excel</x-button>
                <x-button href="{{ route('students.index') }}" variant="secondary" icon="fas fa-arrow-left">Pupils</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data">
                @csrf
                @include('students._form', ['student' => null])
                <div class="mt-6 flex justify-end gap-3">
                    <x-button type="submit" name="after" value="another" variant="secondary" icon="fas fa-plus">Save & admit another</x-button>
                    <x-button type="submit" icon="fas fa-check">Save Pupil</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
