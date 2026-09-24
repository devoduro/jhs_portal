<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Add House') }}
            </h2>
            <x-button href="{{ route('houses.index') }}" variant="secondary" icon="fas fa-arrow-left">
                {{ __('Back to Houses') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('houses.store') }}" class="space-y-6">
                        @csrf

                        <x-input id="name" name="name" type="text" label="House Name" :value="old('name')" required autofocus placeholder="e.g. Aggrey House" />

                        <div>
                            <label for="house_master_id" class="block text-sm font-medium text-gray-700">House Master</label>
                            <select id="house_master_id" name="house_master_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                                <option value="">-- None --</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ old('house_master_id') == $teacher->id ? 'selected' : '' }}>{{ $teacher->name }}</option>
                                @endforeach
                            </select>
                            @error('house_master_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <x-button type="button" variant="secondary" onclick="window.history.back()">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit">
                                {{ __('Add House') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
