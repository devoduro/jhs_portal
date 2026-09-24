<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add Term') }}</h2>
            <x-button href="{{ route('semesters.index') }}" variant="secondary" icon="fas fa-arrow-left">Terms</x-button>
        </div>
    </x-slot>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('semesters.store') }}" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                @csrf
                @include('semesters._form')
                <div class="flex justify-end"><x-button type="submit" icon="fas fa-check">Create Term</x-button></div>
            </form>
        </div>
    </div>
</x-app-layout>
