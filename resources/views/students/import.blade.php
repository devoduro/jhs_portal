<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Import Pupils') }}
            </h2>
            <x-button href="{{ route('students.index') }}" variant="secondary" icon="fas fa-arrow-left">
                {{ __('Back to Pupils') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @if(session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    <div class="mb-8 bg-blue-50 p-4 rounded-md">
                        <h3 class="text-lg font-medium text-blue-800 mb-2">Instructions</h3>
                        <ul class="list-disc pl-5 space-y-1 text-blue-700">
                            <li>Create the classes first (Classes page), then download the template below.</li>
                            <li>One row per pupil. Required columns: <strong>full_name</strong>, <strong>gender</strong> (Male/Female), <strong>class</strong> (exactly as named on the Classes page, e.g. Basic 4A).</li>
                            <li>Optional: admission_number (leave blank to number automatically), date_of_birth, parent_name, relationship, parent_phone, parent_email, address.</li>
                            <li>Re-uploading a row with an existing admission number updates that pupil instead of creating a duplicate.</li>
                        </ul>
                        <div class="mt-4">
                            <x-button href="{{ route('students.import.template') }}" variant="secondary" icon="fas fa-download">
                                {{ __('Download Template') }}
                            </x-button>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-6">
                            <label for="excel_file" class="block text-sm font-medium text-gray-700 mb-2">Excel File</label>
                            <input type="file" id="excel_file" name="excel_file" class="bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" required>
                            @error('excel_file')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end space-x-3">
                            <x-button type="button" variant="secondary" onclick="window.history.back()">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit">
                                {{ __('Import Pupils') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
