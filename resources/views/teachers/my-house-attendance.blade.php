<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('House Attendance') }}
            </h2>
            <x-button href="{{ route('teacher.house-attendance.print', request()->query()) }}" target="_blank" variant="secondary" icon="fas fa-print">
                {{ __('Print') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <p class="text-sm text-gray-500 mb-4">{{ $houses->pluck('name')->join(', ') }}</p>
                    <form method="GET" action="{{ route('teacher.house-attendance') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or index number" class="mt-1 block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Statuses</option>
                                <option value="on_campus" {{ request('status') === 'on_campus' ? 'selected' : '' }}>On Campus</option>
                                <option value="off_campus" {{ request('status') === 'off_campus' ? 'selected' : '' }}>Off Campus</option>
                                <option value="no_record" {{ request('status') === 'no_record' ? 'selected' : '' }}>No Record</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">From</label>
                            <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="mt-1 block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">To</label>
                            <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="mt-1 block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="w-full bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            @if(request()->hasAny(['search', 'status', 'from', 'to']))
                                <a href="{{ route('teacher.house-attendance') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary-600">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            @forelse($report as $date => $rows)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-800">{{ \Illuminate\Support\Carbon::parse($date)->format('l, F j, Y') }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Index Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">House</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($rows as $row)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $row['student']->index_number }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row['student']->full_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row['student']->house->name ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row['time_in'] ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row['time_out'] ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($row['status'] === 'On Campus')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">On Campus</span>
                                            @elseif($row['status'] === 'Off Campus')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Off Campus</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">No Record</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-400">
                    No students assigned to your house(s) yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
