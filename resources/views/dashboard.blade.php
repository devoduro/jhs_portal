@extends('components.app-layout')

@section('title', 'Dashboard')
@section('subtitle', $term ? $term->full_name : 'No current term set')

@section('content')
@php
    $school = \App\Models\Setting::get('institution_name', 'the school');
    $money = fn ($v) => 'GH₵ ' . number_format((float) $v, 2);
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Welcome hero -->
    <div class="col-span-1 md:col-span-3">
        <x-dashboard-hero :title="\App\Support\Greeting::salutation(auth()->user()->name)" :eyebrow="now()->format('l, j F Y')"
            :subtitle="'Here is what is happening at ' . $school . ' today.'">
            <x-slot:chips>
                @if($term?->academicYear)<span><i class="fas fa-calendar-days"></i> {{ $term->academicYear->name }}</span>@endif
                @if($term)<span><i class="fas fa-book-open"></i> {{ $term->name }}@if($termProgress !== null) <span class="text-slate-300">&bull; {{ $termProgress }}% done</span>@endif</span>@endif
                <span><i class="fas fa-user-graduate"></i> {{ number_format($stats['pupils']) }} pupils</span>
                @if($term?->vacation_date)<span><i class="fas fa-umbrella-beach"></i> Vacates {{ $term->vacation_date->format('j M') }}</span>@endif
            </x-slot:chips>
            @can('manage-fees')
                <a class="primary" href="{{ route('fees.index') }}"><i class="fas fa-cash-register text-[#42a5dc]"></i> Receive Fees</a>
            @endcan
            @canany(['enter-scores', 'manage-results'])
                <a href="{{ route('scores.index') }}"><i class="fas fa-pen-to-square"></i> Enter Scores</a>
            @endcanany
            @if($showAcademics)
                <a href="{{ route('report-cards.bulk-form') }}"><i class="fas fa-print"></i> Print Reports</a>
            @endif
        </x-dashboard-hero>
    </div>

    @if(!$term)
        <div class="col-span-1 md:col-span-3 flex flex-wrap items-center justify-between gap-3 rounded-md bg-yellow-50 p-4 ring-1 ring-yellow-200">
            <p class="text-sm text-yellow-800"><i class="fas fa-triangle-exclamation mr-1"></i> No current term is set, so fees and reports have nothing to work with yet.</p>
            @can('manage-semesters')
                <a href="{{ route('semesters.index') }}" class="rounded-md bg-yellow-500 px-3 py-1.5 text-sm font-semibold text-white hover:bg-yellow-600">Set up terms</a>
            @endcan
        </div>
    @endif

    <!-- Quick find -->
    <div class="col-span-1 md:col-span-3">
        <form method="GET" action="{{ auth()->user()->can('manage-fees') ? route('fees.index') : route('students.index') }}"
              class="flex flex-col gap-3 rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center">
            <label for="quick-find" class="text-sm font-semibold text-gray-700 sm:w-40"><i class="fas fa-magnifying-glass mr-1 text-[#42a5dc]"></i> Find a pupil</label>
            <input id="quick-find" type="text" name="search" placeholder="Type a name, admission number or parent's phone and press Enter"
                   class="flex-1 rounded-md border-gray-300 text-sm focus:border-[#42a5dc] focus:ring-[#42a5dc]" autocomplete="off">
            <button type="submit" class="rounded-md bg-[#42a5dc] px-4 py-2 text-sm font-semibold text-white hover:bg-[#2c8fc6]">
                {{ auth()->user()->can('manage-fees') ? 'Open fee account' : 'Search' }}
            </button>
        </form>
    </div>

    <!-- Metric tiles -->
    <div class="col-span-1 md:col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <x-stat-tile color="blue" icon="fa-user-graduate" title="Pupils on Roll"
            :sub="$stats['boys'] . ' boys • ' . $stats['girls'] . ' girls'"
            :value="number_format($stats['pupils'])"
            :badge="$stats['newThisTerm'] ? '+' . $stats['newThisTerm'] . ' this term' : null"
            :href="auth()->user()->can('manage-students') ? route('students.index') : null" />

        @if($showFees && $fees)
            <x-stat-tile color="green" icon="fa-coins" title="Collected Today"
                :sub="$fees['todayCount'] . ' payment(s) • ' . $money($fees['week']) . ' this week'"
                :value="$money($fees['today'])"
                :href="route('fees.report', ['from' => today()->toDateString(), 'to' => today()->toDateString()])" />
            <x-stat-tile color="purple" icon="fa-sack-dollar" title="Collected This Term"
                :sub="'of ' . $money($fees['billed']) . ' billed'"
                :value="$money($fees['collected'])"
                :badge="$fees['rate'] . '% collected'"
                :href="route('fees.report')" />
            <x-stat-tile color="red" icon="fa-circle-exclamation" title="Owed by Parents"
                :sub="$fees['debtors'] . ' pupil(s) with a balance'"
                :value="$money($fees['outstanding'])"
                :href="route('fees.index', ['status' => 'debtors', 'sort' => 'balance'])" />
            <x-stat-tile color="orange" icon="fa-file-invoice" title="Not Yet Billed"
                :sub="$term ? 'Pupils without a ' . $term->name . ' bill' : 'No current term'"
                :value="number_format($fees['unbilled'])"
                :badge="$fees['unbilled'] ? 'Bill now' : 'All billed'"
                :href="$term ? route('fees.billing', ['semester_id' => $term->id]) : null" />
        @endif

        @if($showAcademics)
            <x-stat-tile color="slate" icon="fa-chalkboard-user" title="Classes & Teachers"
                :sub="$stats['unplaced'] ? $stats['unplaced'] . ' pupil(s) not in a class' : 'Every pupil is in a class'"
                :value="$stats['classes'] . ' / ' . $stats['teachers']"
                :href="auth()->user()->can('manage-classes') ? route('class-groups.index') : null" />
        @endif
    </div>

    <!-- Charts -->
    @if($showFees && $fees)
        <div class="col-span-1 md:col-span-2 rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <div class="mb-2 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-700">Billed vs collected by class</h4>
                <a href="{{ route('fees.report') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">Full report <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            @if($fees['byClass']->where('billed', '>', 0)->isEmpty() && $fees['byClass']->where('collected', '>', 0)->isEmpty())
                <p class="flex h-72 items-center justify-center text-sm text-gray-400">Nothing billed for this term yet.</p>
            @else
                <div class="h-72"><canvas id="classChart"></canvas></div>
            @endif
        </div>
        <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <h4 class="mb-2 text-sm font-semibold text-gray-700">How parents paid</h4>
            @if($fees['methods']->isEmpty())
                <p class="flex h-72 items-center justify-center text-sm text-gray-400">No payments this term yet.</p>
            @else
                <div class="h-72"><canvas id="methodChart"></canvas></div>
            @endif
        </div>

        <div class="col-span-1 md:col-span-2 rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <div class="mb-2 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-700">Daily takings — {{ $fees['dailyFrom']->format('j M') }} to {{ now()->format('j M Y') }}</h4>
                <a href="{{ route('fees.report', ['from' => $fees['dailyFrom']->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">Cash book <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="h-64"><canvas id="dailyChart"></canvas></div>
        </div>
        <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <h4 class="mb-2 text-sm font-semibold text-gray-700">Billed by fee item</h4>
            @if($fees['items']->isEmpty())
                <p class="flex h-64 items-center justify-center text-sm text-gray-400">Nothing billed yet.</p>
            @else
                <div class="h-40"><canvas id="itemChart"></canvas></div>
                <ul class="mt-3 space-y-1 text-xs">
                    @foreach($fees['items'] as $i => $item)
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-gray-600"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ ['#42a5dc', '#8cc152', '#f5922f', '#967adc', '#5b6f8f', '#da4453', '#37bc9b', '#e8a838', '#4a89dc', '#d770ad'][$i % 10] }}"></span>{{ $item['item'] }}</span>
                            <span class="font-semibold text-gray-800">{{ number_format($item['total'], 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="col-span-1 {{ $showFees && $fees ? 'md:col-span-2' : 'md:col-span-3' }} rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">Pupils by class</h4>
        @if($levelChart->isEmpty())
            <p class="flex h-64 items-center justify-center text-sm text-gray-400">No pupils yet.</p>
        @else
            <div class="h-64"><canvas id="levelChart"></canvas></div>
        @endif
    </div>
    @if($showFees && $fees)
        <div class="rounded-md bg-white shadow-sm ring-1 ring-gray-100">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <h4 class="text-sm font-semibold text-gray-700">Latest payments</h4>
                <a href="{{ route('fees.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">All pupils</a>
            </div>
            <div class="max-h-72 divide-y divide-gray-100 overflow-y-auto">
                @forelse($fees['recent'] as $payment)
                    <a href="{{ route('fees.show', $payment->student_id) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700"><i class="fas fa-receipt"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-800">{{ $payment->student->full_name ?? '—' }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ $payment->student?->classGroup->name }} • {{ $payment->payment_date->format('j M') }} • {{ $payment->methodLabel() }}</span>
                        </span>
                        <span class="whitespace-nowrap text-sm font-semibold text-gray-800">{{ number_format($payment->amount, 2) }}</span>
                    </a>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-gray-400">No payments recorded yet.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if($showAcademics && $term)
        <!-- Terminal report progress -->
        <div class="col-span-1 md:col-span-2 rounded-md bg-white shadow-sm ring-1 ring-gray-100">
            <div class="border-b border-gray-100 px-4 py-3">
                <h4 class="text-sm font-semibold text-gray-700">Terminal report progress — {{ $term->name }}</h4>
                <p class="text-xs text-gray-500">Share of scores and class-teacher remarks entered for each class.</p>
            </div>
            @if($classProgress->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-gray-400">No classes yet. <a href="{{ route('class-groups.create') }}" class="text-primary-600 hover:underline">Create one</a>.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Class</th>
                                <th class="px-4 py-2 text-right font-medium">Pupils</th>
                                <th class="w-40 px-4 py-2 text-left font-medium">Scores</th>
                                <th class="w-40 px-4 py-2 text-left font-medium">Remarks</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($classProgress as $row)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <span class="font-medium text-gray-800">{{ $row['class']->name }}</span>
                                        <span class="block text-xs text-gray-500">{{ $row['class']->classTeacher->name ?? 'No class teacher' }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ $row['roll'] }}</td>
                                    @foreach(['scores', 'remarks'] as $k)
                                        <td class="px-4 py-2">
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 flex-1 rounded bg-gray-100"><div class="h-2 rounded {{ $row[$k] >= 100 ? 'bg-[#8cc152]' : 'bg-[#42a5dc]' }}" style="width: {{ $row[$k] }}%"></div></div>
                                                <span class="w-9 text-right text-xs text-gray-500">{{ $row[$k] }}%</span>
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-xs">
                                        <a href="{{ route('scores.broadsheet', ['classGroup' => $row['class'], 'semester_id' => $term->id]) }}" class="mr-3 text-primary-600 hover:underline">Broadsheet</a>
                                        <a href="{{ route('report-cards.bulk-print', ['class_group_id' => $row['class']->id, 'semester_id' => $term->id]) }}" target="_blank" class="text-primary-600 hover:underline">Reports</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Top pupils + calendar -->
        <div class="space-y-6">
            <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <h4 class="mb-3 text-sm font-semibold text-gray-700">Top pupils this term</h4>
                <div class="space-y-3">
                    @forelse($topPupils as $i => $row)
                        <a href="{{ route('students.show', $row['student']) }}" class="flex items-center gap-3 hover:opacity-80">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white {{ ['bg-[#f5922f]', 'bg-[#5b6f8f]', 'bg-[#967adc]'][$i] ?? 'bg-[#42a5dc]' }}">{{ $i + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-gray-800">{{ $row['student']->full_name }}</span>
                                <span class="block text-xs text-gray-500">{{ $row['student']->classGroup->name ?? '' }}</span>
                            </span>
                            <span class="text-sm font-semibold text-gray-700">{{ $row['average'] }}%</span>
                        </a>
                    @empty
                        <p class="py-4 text-center text-sm text-gray-400">Appears once scores are entered.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <h4 class="mb-3 text-sm font-semibold text-gray-700">School calendar</h4>
                <div class="space-y-4 text-sm">
                    @if($term->academicYear)
                        <div class="flex items-start gap-4">
                            <div class="min-w-[50px] text-center"><div class="text-xs text-gray-500">Year</div><div class="text-lg font-bold gradient-text">{{ $yearProgress ?? '—' }}{{ $yearProgress !== null ? '%' : '' }}</div></div>
                            <div><p class="font-medium text-gray-800">{{ $term->academicYear->name }}</p>
                                <p class="text-xs text-gray-500">{{ $term->academicYear->start_date?->format('j M Y') }} – {{ $term->academicYear->end_date?->format('j M Y') }}</p></div>
                        </div>
                    @endif
                    <div class="flex items-start gap-4">
                        <div class="min-w-[50px] text-center"><div class="text-xs text-gray-500">Term</div><div class="text-lg font-bold gradient-text">{{ $termProgress ?? '—' }}{{ $termProgress !== null ? '%' : '' }}</div></div>
                        <div><p class="font-medium text-gray-800">{{ $term->name }}</p>
                            <p class="text-xs text-gray-500">Reopened {{ $term->start_date?->format('j M Y') }} • Vacates {{ ($term->vacation_date ?? $term->end_date)?->format('j M Y') }}</p>
                            @if($term->next_term_begins)<p class="text-xs text-gray-500">Next term begins {{ $term->next_term_begins->format('j M Y') }}</p>@endif
                        </div>
                    </div>
                </div>
                @can('manage-semesters')
                    <div class="mt-4 border-t border-gray-100 pt-3">
                        <a href="{{ route('semesters.edit', $term) }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">Edit term dates <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                @endcan
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
window.addEventListener('load', function () {
    if (typeof Chart === 'undefined') return;
    const C = { blue: '#42a5dc', green: '#8cc152', orange: '#f5922f', red: '#da4453', slate: '#5b6f8f', purple: '#967adc' };
    const palette = ['#42a5dc', '#8cc152', '#f5922f', '#967adc', '#5b6f8f', '#da4453', '#37bc9b', '#e8a838', '#4a89dc', '#d770ad'];
    const cedi = v => 'GH₵ ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = { callbacks: { label: c => (c.dataset.label ? c.dataset.label + ': ' : c.label + ': ') + cedi(c.raw) } };
    const cediAxis = { beginAtZero: true, ticks: { callback: v => 'GH₵ ' + v.toLocaleString() } };

    const levels = @json($levelChart);
    const levelCanvas = document.getElementById('levelChart');
    if (levelCanvas) new Chart(levelCanvas, {
        type: 'bar',
        data: { labels: levels.map(l => l.label), datasets: [{ label: 'Pupils', data: levels.map(l => l.count), backgroundColor: levels.map((_, i) => palette[i % 5]), borderRadius: 3 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
    });

    @if($showFees && $fees)
        const rows = @json($fees['byClass']->values());
        const classCanvas = document.getElementById('classChart');
        if (classCanvas) new Chart(classCanvas, {
            type: 'bar',
            data: { labels: rows.map(r => r.class), datasets: [
                { label: 'Billed', data: rows.map(r => r.billed), backgroundColor: '#42a5dc55', borderColor: C.blue, borderWidth: 1, borderRadius: 3 },
                { label: 'Collected', data: rows.map(r => r.collected), backgroundColor: C.green, borderRadius: 3 },
            ] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: money, legend: { position: 'bottom' } }, scales: { y: cediAxis, x: { grid: { display: false } } } },
        });

        const methods = @json($fees['methods']);
        const methodCanvas = document.getElementById('methodChart');
        if (methodCanvas) new Chart(methodCanvas, {
            type: 'doughnut',
            data: { labels: methods.map(m => m.method), datasets: [{ data: methods.map(m => m.total), backgroundColor: [C.green, C.orange, C.blue, C.purple, C.slate], borderWidth: 2, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' }, tooltip: money } },
        });

        const items = @json($fees['items']);
        const itemCanvas = document.getElementById('itemChart');
        if (itemCanvas) new Chart(itemCanvas, {
            type: 'doughnut',
            data: { labels: items.map(i => i.item), datasets: [{ data: items.map(i => i.total), backgroundColor: items.map((_, i) => palette[i % palette.length]), borderWidth: 2, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false }, tooltip: money } },
        });

        // Every day in the range, including days with no payments.
        const dailyRaw = @json($fees['daily']->mapWithKeys(fn ($d) => [$d['date']->format('Y-m-d') => $d['total']]));
        const start = new Date('{{ $fees['dailyFrom']->format('Y-m-d') }}T00:00:00'), today = new Date();
        const days = [];
        for (let d = new Date(start); d <= today; d.setDate(d.getDate() + 1)) days.push(new Date(d));
        const key = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: days.map(d => d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })),
                datasets: [{ label: 'Collected', data: days.map(d => Number(dailyRaw[key(d)] || 0)), borderColor: C.purple, backgroundColor: '#967adc22', fill: true, tension: .35, pointRadius: 3, pointBackgroundColor: C.purple }],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: money }, scales: { y: cediAxis, x: { grid: { display: false } } } },
        });
    @endif
});
</script>
@endpush
