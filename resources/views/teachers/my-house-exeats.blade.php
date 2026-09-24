<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('House Exeats') }}
            </h2>
            <x-button href="{{ route('teacher.house-exeats.create') }}" icon="fas fa-plus">
                {{ __('Grant Exeat') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <p class="text-sm text-gray-500 mb-4">{{ $houses->pluck('name')->join(', ') }}</p>

                    <form method="GET" action="{{ route('teacher.house-exeats') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or index number" class="block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <select name="status" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Statuses</option>
                                <option value="upcoming" {{ request('status') === 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">From</label>
                            <input type="date" name="from" value="{{ request('from') }}" class="block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">To</label>
                            <input type="date" name="to" value="{{ request('to') }}" class="block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="w-full bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            @if(request()->hasAny(['search', 'status', 'from', 'to']))
                                <a href="{{ route('teacher.house-exeats') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary-600">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                @if($exeats->isEmpty())
                    <div class="text-center py-12">
                        <i class="fas fa-door-open text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-500">No exeats granted yet</h3>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SMS</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($exeats as $exeat)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                <x-student-photo :student="$exeat->student" class="w-10 h-10 rounded-full" />
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $exeat->student->full_name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $exeat->student->index_number }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $exeat->reason }}">{{ $exeat->reason }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $exeat->start_date->format('M j, Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $exeat->end_date->format('M j, Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @php $status = $exeat->status(); @endphp
                                            @if($status === 'Active')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                            @elseif($status === 'Upcoming')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Upcoming</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Completed</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($exeat->sms_log_id)
                                                <span class="text-green-600"><i class="fas fa-check-circle"></i> Sent</span>
                                            @else
                                                <span class="text-gray-400"><i class="fas fa-times-circle"></i> Not sent</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">
                        {{ $exeats->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
