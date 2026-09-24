<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Continuous Assessment') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <p class="text-sm text-gray-500 mb-4">Select a subject to enter or review Continuous Assessment scores.</p>

                    <form method="GET" action="{{ route('continuous-assessment.index') }}" class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by code or title..." class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="flex flex-col md:flex-row gap-4">
                            <select name="programme_id" onchange="this.form.submit()" class="border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 px-4 py-2">
                                <option value="">All Programmes</option>
                                @foreach($programmes as $programme)
                                    <option value="{{ $programme->id }}" {{ (string) request('programme_id') === (string) $programme->id ? 'selected' : '' }}>{{ $programme->name }}</option>
                                @endforeach
                            </select>
                            <select name="semester_id" onchange="this.form.submit()" class="border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 px-4 py-2">
                                <option value="">All Semesters</option>
                                @foreach($semesters as $semester)
                                    <option value="{{ $semester->id }}" {{ (string) request('semester_id') === (string) $semester->id ? 'selected' : '' }}>{{ $semester->name }} - {{ $semester->academicYear->name ?? '' }}</option>
                                @endforeach
                            </select>
                            <select name="level" onchange="this.form.submit()" class="border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 px-4 py-2">
                                <option value="">All Forms</option>
                                @foreach(\App\Support\FormLabel::OPTIONS as $levelOption => $levelLabel)
                                    <option value="{{ $levelOption }}" {{ (string) request('level') === (string) $levelOption ? 'selected' : '' }}>{{ $levelLabel }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            @if(request()->hasAny(['search', 'programme_id', 'semester_id', 'level']))
                                <a href="{{ route('continuous-assessment.index') }}" class="inline-flex items-center px-4 py-2 text-sm text-gray-600 hover:text-primary-600">
                                    Clear
                                </a>
                            @endif
                            <a href="{{ route('continuous-assessment.export.excel', request()->query()) }}" class="bg-green-600 text-white rounded-md px-4 py-2 text-sm hover:bg-green-700 text-center">
                                <i class="fas fa-file-excel mr-1"></i> Excel
                            </a>
                            <a href="{{ route('continuous-assessment.export.pdf', request()->query()) }}" class="bg-red-600 text-white rounded-md px-4 py-2 text-sm hover:bg-red-700 text-center">
                                <i class="fas fa-file-pdf mr-1"></i> PDF
                            </a>
                        </div>
                    </form>

                    @if($subjects->isEmpty())
                        <div class="text-center py-8 text-gray-400">
                            No subjects available.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Form</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($subjects as $subject)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $subject->code }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $subject->title }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($subject->level)
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                                        {{ \App\Support\FormLabel::label($subject->level) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">N/A</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $subject->semester->name ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $subject->teachers->pluck('name')->join(', ') ?: 'Not assigned' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('continuous-assessment.show', $subject) }}" class="text-primary-600 hover:text-primary-900">
                                                    <i class="fas fa-clipboard-list"></i> Enter Scores
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
