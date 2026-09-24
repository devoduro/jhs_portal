<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My House') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Profile summary -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center gap-4">
                @if($teacher->profile_photo)
                    <img src="{{ asset('storage/' . $teacher->profile_photo) }}" alt="{{ $teacher->name }}" class="w-16 h-16 rounded-full object-cover">
                @else
                    <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                        <i class="fas fa-user text-2xl"></i>
                    </div>
                @endif
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $teacher->name }}</h3>
                    <p class="text-sm text-gray-500">House Master &bull; {{ $houses->pluck('name')->join(', ') }}</p>
                </div>
            </div>

            @foreach($houses as $house)
                @php
                    $maleCount = $house->students->where('gender', 'Male')->count();
                    $femaleCount = $house->students->where('gender', 'Female')->count();
                @endphp
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-semibold text-gray-800"><i class="fas fa-home text-primary-500 mr-2"></i>{{ $house->name }}</h4>
                            <div class="flex gap-4">
                                <a href="{{ route('teacher.house-exeats') }}" class="text-sm text-primary-600 hover:text-primary-700">
                                    <i class="fas fa-door-open mr-1"></i> Exeats
                                </a>
                                <a href="{{ route('teacher.house-attendance') }}" class="text-sm text-primary-600 hover:text-primary-700">
                                    <i class="fas fa-clipboard-list mr-1"></i> Attendance
                                </a>
                                <a href="{{ route('student-houses.print', ['house_id' => $house->id]) }}" target="_blank" class="text-sm text-primary-600 hover:text-primary-700">
                                    <i class="fas fa-print mr-1"></i> Print List
                                </a>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-sm text-gray-500">Total Students</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $house->students->count() }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-sm text-gray-500">Male</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $maleCount }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-sm text-gray-500">Female</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $femaleCount }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-sm text-gray-500">Boarders</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $house->students->where('boarding_status', 'boarder')->count() }}</p>
                            </div>
                        </div>

                        @if($house->students->isEmpty())
                            <div class="text-center py-8">
                                <i class="fas fa-user-graduate text-gray-300 text-4xl mb-3"></i>
                                <p class="text-gray-500">No students have been assigned to this house yet.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Programme</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boarding Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($house->students as $student)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center gap-3">
                                                        <x-student-photo :student="$student" class="w-10 h-10 rounded-full" />
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900">{{ $student->full_name }}</div>
                                                            <div class="text-xs text-gray-500">{{ $student->index_number }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->programme->name ?? 'N/A' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->classGroup->name ?? 'Unassigned' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    @if($student->boarding_status === 'boarder')
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Boarder</span>
                                                    @elseif($student->boarding_status === 'day')
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Day Student</span>
                                                    @else
                                                        <span class="text-gray-400">Not Set</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
