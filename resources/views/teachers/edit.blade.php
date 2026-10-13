<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Teacher') }} — {{ $teacher->name }}</h2>
            <x-button href="{{ route('teachers.index') }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Back to Teachers') }}</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('teachers.update', $teacher) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('teachers._form')
                <div class="sticky bottom-0 -mx-4 mt-6 flex items-center justify-end gap-3 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:shadow-sm">
                    <x-button href="{{ route('teachers.show', $teacher) }}" variant="secondary">{{ __('Cancel') }}</x-button>
                    <x-button type="submit" icon="fas fa-check">{{ __('Update Teacher') }}</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
