<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Grant Exeat') }}
            </h2>
            <x-button href="{{ route('teacher.house-exeats') }}" variant="secondary" icon="fas fa-arrow-left">
                {{ __('Back to Exeats') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-700 text-sm">
                        Only boarding students in {{ $houses->pluck('name')->join(', ') }} are eligible. Their emergency contact will be texted the reason and dates automatically.
                    </div>

                    @if($students->isEmpty())
                        <div class="text-center py-8 text-gray-400">
                            No boarding students found in your house(s).
                        </div>
                    @else
                        <form method="POST" action="{{ route('teacher.house-exeats.store') }}" class="space-y-6">
                            @csrf

                            <div>
                                <label for="student_id" class="block text-sm font-medium text-gray-700">Student (Boarders Only)</label>
                                <select id="student_id" name="student_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md" required>
                                    <option value="">Select a boarder</option>
                                    @foreach($students as $student)
                                        <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                            {{ $student->full_name }} ({{ $student->index_number }}) &mdash; {{ $student->house->name ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('student_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                                <textarea id="reason" name="reason" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" required>{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" required>
                                    @error('start_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="end_date" class="block text-sm font-medium text-gray-700">Return Date</label>
                                    <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" required>
                                    @error('end_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <x-button type="button" variant="secondary" onclick="window.history.back()">
                                    {{ __('Cancel') }}
                                </x-button>
                                <x-button type="submit">
                                    {{ __('Grant Exeat') }}
                                </x-button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
