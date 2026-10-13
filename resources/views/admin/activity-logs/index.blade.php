<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Activity Logs') }}</h2>
    </x-slot>

    @php
        $filtersOn = collect(['q', 'action', 'user_id', 'from', 'to'])->contains(fn ($k) => request()->filled($k));
        $peak = max(1, $week->max('count'));
        $deviceOf = fn ($ua) => \App\Http\Controllers\ActivityLogController::device($ua);
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-dashboard-hero title="Activity Logs" eyebrow="Who did what, and when"
                subtitle="Every sign-in and every change staff make - payments, billing, scores, settings - is recorded here.">
                <x-slot:chips>
                    <span><i class="fas fa-bolt"></i> {{ number_format($stats['today']) }} events today</span>
                    <span><i class="fas fa-users"></i> {{ $stats['users_today'] }} active users today</span>
                    <span><i class="fas fa-database"></i> {{ number_format($stats['total']) }} on record</span>
                </x-slot:chips>
                <a class="primary" href="{{ route('activity-logs.export', request()->query()) }}"><i class="fas fa-file-csv text-[#42a5dc]"></i> Export CSV</a>
            </x-dashboard-hero>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-stat-tile color="blue" icon="fa-bolt" title="Events Today" :value="number_format($stats['today'])" sub="Sign-ins and changes" :href="route('activity-logs.index', ['from' => today()->toDateString()])" />
                <x-stat-tile color="green" icon="fa-right-to-bracket" title="Sign-ins (7 days)" :value="number_format($stats['logins_week'])" sub="Staff and pupil logins" :href="route('activity-logs.index', ['action' => 'login'])" />
                <x-stat-tile color="purple" icon="fa-pen-to-square" title="Changes Today" :value="number_format($stats['changes_today'])" sub="Payments, scores, settings..." :href="route('activity-logs.index', ['action' => 'changes', 'from' => today()->toDateString()])" />
                <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Last 7 days</p>
                    <div class="flex h-14 items-end gap-2">
                        @foreach($week as $day)
                            <div class="flex flex-1 flex-col items-center gap-1" title="{{ $day['count'] }} events">
                                <div class="w-full rounded-t bg-[#42a5dc]/70" style="height: {{ max(4, round($day['count'] / $peak * 40)) }}px"></div>
                                <span class="text-[10px] text-gray-400">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('activity-logs.index') }}" class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                    <div class="md:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Search</label>
                        <div class="relative">
                            <i class="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="q" value="{{ request('q') }}" placeholder="Pupil, receipt, IP address..." class="w-full rounded-md border-gray-300 pl-9 text-sm">
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Who</label>
                        <select name="user_id" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Everyone</option>
                            @foreach($people as $person)
                                <option value="{{ $person->id }}" @selected(request('user_id') == $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">What</label>
                        <select name="action" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Everything</option>
                            <option value="changes" @selected(request('action') === 'changes')>All changes (not sign-ins)</option>
                            @foreach($actions as $key => [$label])
                                @if(in_array($key, $presentActions, true) || request('action') === $key)
                                    <option value="{{ $key }}" @selected(request('action') === $key)>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">From</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">To</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="flex gap-2 md:col-span-1">
                        <button type="submit" class="flex-1 rounded-md bg-[#42a5dc] px-3 py-2 text-sm font-semibold text-white hover:bg-[#2c8fc6]" title="Filter"><i class="fas fa-filter"></i></button>
                        @if($filtersOn)<a href="{{ route('activity-logs.index') }}" class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-600 hover:bg-gray-200" title="Clear"><i class="fas fa-rotate-left"></i></a>@endif
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="text-gray-500">Quick:</span>
                    <a href="{{ route('activity-logs.index', ['from' => today()->toDateString()]) }}" class="rounded-full bg-gray-100 px-3 py-1 text-gray-600 hover:bg-gray-200">Today</a>
                    <a href="{{ route('activity-logs.index', ['from' => today()->subDays(6)->toDateString()]) }}" class="rounded-full bg-gray-100 px-3 py-1 text-gray-600 hover:bg-gray-200">Last 7 days</a>
                    <a href="{{ route('activity-logs.index', ['action' => 'changes']) }}" class="rounded-full bg-gray-100 px-3 py-1 text-gray-600 hover:bg-gray-200">Changes only</a>
                    <a href="{{ route('activity-logs.index', ['action' => 'payment']) }}" class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700 hover:bg-emerald-100">Payments</a>
                    <a href="{{ route('activity-logs.index', ['action' => 'user']) }}" class="rounded-full bg-red-50 px-3 py-1 text-red-700 hover:bg-red-100">Accounts & roles</a>
                </div>
            </form>

            <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 text-sm text-gray-500">
                    <span>{{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}{{ $filtersOn ? ' match your filters' : '' }}</span>
                    <form method="GET" action="{{ route('activity-logs.index') }}" class="flex items-center gap-2">
                        @foreach(request()->except(['per_page', 'page']) as $k => $v)@if(!is_array($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
                        <label class="text-xs">Per page</label>
                        <select name="per_page" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-xs">
                            @foreach([20, 50, 100, 200] as $n)<option value="{{ $n }}" @selected(request('per_page', 20) == $n)>{{ $n }}</option>@endforeach
                        </select>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">When</th>
                                <th class="px-4 py-3 text-left font-medium">Who</th>
                                <th class="px-4 py-3 text-left font-medium">What</th>
                                <th class="px-4 py-3 text-left font-medium">Details</th>
                                <th class="px-4 py-3 text-left font-medium">From</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($logs as $log)
                                @php
                                    [$label, $icon, $badge] = $actions[$log->action] ?? [ucfirst($log->action), 'fa-circle-dot', 'bg-gray-100 text-gray-600 ring-gray-200'];
                                    $name = $log->user->name ?? 'System';
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="block text-gray-900">{{ $log->created_at->format('d M Y, H:i') }}</span>
                                        <span class="block text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <a href="{{ route('activity-logs.index', ['user_id' => $log->user_id]) }}" class="flex items-center gap-2.5">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
                                            <span class="font-medium text-gray-900 hover:text-[#2c8fc6]">{{ $name }}</span>
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <a href="{{ route('activity-logs.index', ['action' => $log->action]) }}" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $badge }}">
                                            <i class="fas {{ $icon }}"></i> {{ $label }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $log->description }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                        <span class="block">{{ $log->ip_address }}</span>
                                        <span class="block text-gray-400">{{ $deviceOf($log->user_agent) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-16 text-center">
                                        <i class="fas fa-clock-rotate-left mb-3 block text-3xl text-gray-300"></i>
                                        <p class="font-medium text-gray-600">No activity matches these filters.</p>
                                        <a href="{{ route('activity-logs.index') }}" class="mt-1 inline-block text-sm text-[#2c8fc6] hover:underline">Clear filters</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($logs->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $logs->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
