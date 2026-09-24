<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Parent Notifications') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <p class="text-sm text-gray-500 mb-4">Every automatic SMS sent to a student's emergency contact - exeat notices and campus check-in alerts. Bulk campaigns (sent to the student's own number) are tracked separately under Sent Messages.</p>

                    <form method="GET" action="{{ route('sms.parent-notifications') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                        <div>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or index number" class="block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <select name="type" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Types</option>
                                <option value="exeat" {{ request('type') === 'exeat' ? 'selected' : '' }}>Exeat</option>
                                <option value="campus_checkin" {{ request('type') === 'campus_checkin' ? 'selected' : '' }}>Campus Check-In</option>
                                <option value="grades" {{ request('type') === 'grades' ? 'selected' : '' }}>Grades</option>
                            </select>
                        </div>
                        <div>
                            <select name="house_id" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Houses</option>
                                @foreach($houses as $house)
                                    <option value="{{ $house->id }}" {{ (int) request('house_id') === $house->id ? 'selected' : '' }}>{{ $house->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <select name="status" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Statuses</option>
                                <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
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
                        <div class="md:col-span-6 flex gap-2">
                            <button type="submit" class="bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            @if(request()->hasAny(['search', 'type', 'house_id', 'status', 'from', 'to']))
                                <a href="{{ route('sms.parent-notifications') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary-600">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if($logs->isEmpty())
                    <div class="text-center py-12">
                        <i class="fas fa-sms text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-500">No parent notifications found</h3>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">House</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($logs as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                @if($log->student)
                                                    <x-student-photo :student="$log->student" class="w-8 h-8 rounded-full" />
                                                @endif
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $log->student->full_name ?? 'Unknown' }}</div>
                                                    <div class="text-xs text-gray-500">{{ $log->student->index_number ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->student->house->name ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @php
                                                $typeColors = [
                                                    'exeat' => 'bg-purple-100 text-purple-800',
                                                    'campus_checkin' => 'bg-teal-100 text-teal-800',
                                                    'grades' => 'bg-indigo-100 text-indigo-800',
                                                ];
                                            @endphp
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $typeColors[$log->type] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ \App\Models\SmsLog::TYPE_LABELS[$log->type] ?? $log->type }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->phone }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $log->message }}">{{ $log->message }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($log->success)
                                                <span class="text-green-600"><i class="fas fa-check-circle"></i> Sent</span>
                                            @else
                                                <span class="text-red-600" title="{{ $log->response_message }}"><i class="fas fa-times-circle"></i> Failed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
