<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Pupil') }} — {{ $student->full_name }}</h2>
            <x-button href="{{ route('students.show', $student) }}" variant="secondary" icon="fas fa-arrow-left">Back</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('students._form')
                <div class="mt-6 flex justify-end">
                    <x-button type="submit" icon="fas fa-check">Save Changes</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
