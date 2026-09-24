<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Print Report Cards') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-md px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">1. Choose a Class</h3>
                    <p class="text-sm text-gray-500 mb-4">Pick a class to print report cards for every student in it, or load the class first to select specific students below.</p>

                    <form method="GET" action="{{ route('report-cards.bulk-form') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <select name="class_group_id" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Select a class&hellip;</option>
                                @foreach($classGroups as $group)
                                    <option value="{{ $group->id }}" {{ (int) request('class_group_id') === $group->id ? 'selected' : '' }}>
                                        {{ $group->name }} &mdash; {{ \App\Support\FormLabel::label($group->level) }} {{ $group->programme->name ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-gray-600 text-white rounded-md px-4 py-2 text-sm hover:bg-gray-700 w-full">
                                <i class="fas fa-users mr-1"></i> Load Class
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @if(request('class_group_id') && $students->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-1">2. Print</h3>
                        <p class="text-sm text-gray-500 mb-4">Optionally limit to one semester, then print the whole class or just the students you select.</p>

                        <form method="GET" action="{{ route('report-cards.bulk-print') }}" target="_blank">
                            <div class="flex flex-wrap items-end gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                                    <select name="semester_id" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                        <option value="">All Semesters</option>
                                        @foreach($semesters as $semester)
                                            <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" onclick="document.querySelectorAll('.report-card-student-check').forEach(c => c.checked = true)" class="text-sm text-primary-600 hover:underline">Select All</button>
                                <button type="button" onclick="document.querySelectorAll('.report-card-student-check').forEach(c => c.checked = false)" class="text-sm text-primary-600 hover:underline">Deselect All</button>
                            </div>

                            <div class="overflow-x-auto border border-gray-200 rounded-md mb-4">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 w-10"></th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Index Number</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($students as $student)
                                            <tr>
                                                <td class="px-4 py-2">
                                                    <input type="checkbox" class="report-card-student-check" name="student_ids[]" value="{{ $student->id }}" checked>
                                                </td>
                                                <td class="px-4 py-2 text-sm text-gray-900">{{ $student->full_name }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-500">{{ $student->index_number }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" class="bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700">
                                    <i class="fas fa-print mr-1"></i> Print Selected
                                </button>
                                <a href="{{ route('report-cards.bulk-print', ['class_group_id' => request('class_group_id')] + (request('semester_id') ? ['semester_id' => request('semester_id')] : [])) }}"
                                   target="_blank"
                                   class="bg-gray-600 text-white rounded-md px-4 py-2 text-sm hover:bg-gray-700">
                                    <i class="fas fa-print mr-1"></i> Print Whole Class
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif(request('class_group_id'))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500">No students are assigned to this class yet.</div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
