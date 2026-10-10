@extends('components.app-layout')

@section('title', 'Fee Collection Report')
@section('subtitle', $term ? $term->full_name : 'No term selected')

@section('content')
@php
    $money = fn ($v) => 'GH₵ ' . number_format((float) $v, 2);
    $rate = $grand['billed'] > 0 ? round($grand['collected'] / $grand['billed'] * 100, 1) : 0;
    $periodTotal = (float) $payments->sum('amount');
    $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
    $debtors = $rows->sum('debtors');
    $bestClass = $rows->where('billed', '>', 0)->sortByDesc('percentage')->first();
    $palette = ['#42a5dc', '#8cc152', '#f5922f', '#967adc', '#5b6f8f', '#da4453', '#37bc9b', '#e8a838', '#4a89dc', '#d770ad'];
@endphp

<div class="space-y-6">
    <x-dashboard-hero title="Fee Collection Report" :eyebrow="$term ? $term->full_name : 'No term selected'"
        :subtitle="'Billed vs collected for the term, and every payment received from ' . $from->format('j M') . ' to ' . $to->format('j M Y') . '.'">
        <x-slot:chips>
            <span><i class="fas fa-chart-pie"></i> {{ $rate }}% collected</span>
            <span><i class="fas fa-receipt"></i> {{ $payments->count() }} payment(s) in period</span>
            @if($bestClass)<span><i class="fas fa-trophy"></i> Best: {{ $bestClass['class'] }} ({{ $bestClass['percentage'] }}%)</span>@endif
        </x-slot:chips>
        <a class="primary" href="{{ route('fees.report.print', request()->query() + ['semester_id' => $term?->id]) }}" target="_blank"><i class="fas fa-print text-[#42a5dc]"></i> Print</a>
        <a href="{{ route('fees.report.export', request()->query() + ['semester_id' => $term?->id]) }}"><i class="fas fa-file-excel"></i> Excel</a>
        <a href="{{ route('fees.index', ['semester_id' => $term?->id, 'status' => 'debtors', 'sort' => 'balance']) }}"><i class="fas fa-user-clock"></i> Pupils owing</a>
    </x-dashboard-hero>

    <!-- Filters -->
    <form method="GET" action="{{ route('fees.report') }}" class="flex flex-col gap-3 rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100 md:flex-row md:flex-wrap md:items-end">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Term</label>
            <select name="semester_id" class="rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                @foreach($terms as $t)
                    <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Cash book from</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">to</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="rounded-md border-gray-300 text-sm">
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5">
            @foreach(['Today' => [today(), today()], 'This week' => [now()->startOfWeek(), today()], 'This month' => [now()->startOfMonth(), today()], 'Whole term' => [$term?->start_date ?? now()->startOfMonth(), today()]] as $label => [$a, $b])
                <a href="{{ route('fees.report', ['semester_id' => $term?->id, 'from' => $a->format('Y-m-d'), 'to' => $b->format('Y-m-d')]) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $from->isSameDay($a) && $to->isSameDay($b) ? 'bg-[#42a5dc] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $label }}</a>
            @endforeach
        </div>
        <button type="submit" class="rounded-md bg-[#42a5dc] px-4 py-2 text-sm font-semibold text-white hover:bg-[#2c8fc6] md:ml-auto"><i class="fas fa-rotate mr-1"></i> Show</button>
    </form>

    <!-- Tiles -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile color="blue" icon="fa-file-invoice" title="Billed" :sub="$grand['pupils'] . ' pupils • ' . ($term?->name ?? 'term')" :value="$money($grand['billed'])" />
        <x-stat-tile color="green" icon="fa-sack-dollar" title="Collected" sub="Paid against this term" :value="$money($grand['collected'])" :badge="$rate . '% collected'" />
        <x-stat-tile color="red" icon="fa-circle-exclamation" title="Still Owed" :sub="$debtors . ' pupil(s), all terms'" :value="$money($grand['outstanding'])"
            :href="route('fees.index', ['semester_id' => $term?->id, 'status' => 'debtors', 'sort' => 'balance'])" />
        <x-stat-tile color="purple" icon="fa-cash-register" title="Cash Book Period" :sub="$from->format('j M') . ' – ' . $to->format('j M') . ' • ' . $payments->count() . ' receipts'"
            :value="$money($periodTotal)" :badge="'≈ ' . $money($periodTotal / $days) . '/day'" />
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100 lg:col-span-2">
            <h4 class="mb-2 text-sm font-semibold text-gray-700">Billed vs collected by class</h4>
            @if($rows->isEmpty())
                <p class="flex h-72 items-center justify-center text-sm text-gray-400">No bills for this term yet.</p>
            @else
                <div class="h-72"><canvas id="classChart"></canvas></div>
            @endif
        </div>
        <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <h4 class="mb-2 text-sm font-semibold text-gray-700">How parents paid</h4>
            @if($methods->isEmpty())
                <p class="flex h-72 items-center justify-center text-sm text-gray-400">No payments yet.</p>
            @else
                <div class="h-72"><canvas id="methodChart"></canvas></div>
            @endif
        </div>
        <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100 lg:col-span-2">
            <h4 class="mb-2 text-sm font-semibold text-gray-700">Daily takings — {{ $from->format('j M') }} to {{ $to->format('j M Y') }}</h4>
            @if($daily->isEmpty())
                <p class="flex h-64 items-center justify-center text-sm text-gray-400">No payments in this period.</p>
            @else
                <div class="h-64"><canvas id="dailyChart"></canvas></div>
            @endif
        </div>
        <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <h4 class="mb-2 text-sm font-semibold text-gray-700">Billed by fee item</h4>
            @if($items->isEmpty())
                <p class="flex h-64 items-center justify-center text-sm text-gray-400">Nothing billed yet.</p>
            @else
                <div class="h-44"><canvas id="itemChart"></canvas></div>
                <ul class="mt-3 space-y-1 text-xs">
                    @foreach($items->sortByDesc('total')->values() as $i => $item)
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-gray-600"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $palette[$i % count($palette)] }}"></span>{{ $item['item'] }}</span>
                            <span class="font-semibold text-gray-800">{{ number_format($item['total'], 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <!-- By class -->
    <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-md bg-[#42a5dc] text-white"><i class="fas fa-chalkboard"></i></span>
            <div>
                <h3 class="font-semibold text-gray-900">By class</h3>
                <p class="text-xs text-gray-500">{{ $term?->full_name }} — click a class to see its pupils</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Class</th>
                        <th class="px-5 py-3 text-right font-medium">Pupils</th>
                        <th class="px-5 py-3 text-right font-medium">Owing</th>
                        <th class="px-5 py-3 text-right font-medium">Billed</th>
                        <th class="px-5 py-3 text-right font-medium">Collected</th>
                        <th class="w-56 px-5 py-3 text-left font-medium">% collected</th>
                        <th class="px-5 py-3 text-right font-medium">Outstanding</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                        @php $classId = optional($classLookup[$row['class']] ?? null)->id; @endphp
                        <tr class="hover:bg-[#42a5dc]/5">
                            <td class="px-5 py-3 font-medium">
                                @if($classId)
                                    <a href="{{ route('fees.index', ['semester_id' => $term?->id, 'class_group_id' => $classId]) }}" class="text-gray-900 hover:text-[#2c8fc6]">{{ $row['class'] }}</a>
                                @else
                                    <span class="text-gray-500">{{ $row['class'] }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right text-gray-600">{{ $row['pupils'] }}</td>
                            <td class="px-5 py-3 text-right {{ $row['debtors'] ? 'text-red-600' : 'text-gray-400' }}">{{ $row['debtors'] }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['billed'], 2) }}</td>
                            <td class="px-5 py-3 text-right font-medium text-green-700">{{ number_format($row['collected'], 2) }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-2 rounded-full {{ $row['percentage'] >= 100 ? 'bg-[#8cc152]' : ($row['percentage'] >= 50 ? 'bg-[#42a5dc]' : 'bg-[#f5922f]') }}" style="width: {{ min(100, $row['percentage']) }}%"></div>
                                    </div>
                                    <span class="w-12 text-right text-xs font-semibold text-gray-700">{{ $row['percentage'] }}%</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right font-semibold {{ $row['outstanding'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($row['outstanding'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No pupils or bills for this term.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot class="bg-gray-50 font-semibold text-gray-900">
                        <tr>
                            <td class="px-5 py-3">Whole school</td>
                            <td class="px-5 py-3 text-right">{{ $grand['pupils'] }}</td>
                            <td class="px-5 py-3 text-right">{{ $debtors }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($grand['billed'], 2) }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($grand['collected'], 2) }}</td>
                            <td class="px-5 py-3 text-left text-xs">{{ $rate }}%</td>
                            <td class="px-5 py-3 text-right">{{ number_format($grand['outstanding'], 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- Cash book -->
    <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-md bg-[#967adc] text-white"><i class="fas fa-book-open"></i></span>
                <div>
                    <h3 class="font-semibold text-gray-900">Cash book</h3>
                    <p class="text-xs text-gray-500">{{ $from->format('D j M Y') }} – {{ $to->format('D j M Y') }}</p>
                </div>
            </div>
            <span class="rounded-full bg-[#967adc]/10 px-3 py-1 text-sm font-semibold text-[#7d62c4]">{{ $money($periodTotal) }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Receipt</th>
                        <th class="px-5 py-3 text-left font-medium">Pupil</th>
                        <th class="px-5 py-3 text-left font-medium">Mode</th>
                        <th class="px-5 py-3 text-left font-medium">Received by</th>
                        <th class="px-5 py-3 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($payments->groupBy(fn ($p) => $p->payment_date->format('Y-m-d'))->sortKeysDesc() as $day => $dayPayments)
                        <tr class="bg-gray-50/70">
                            <td colspan="4" class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <i class="fas fa-calendar-day mr-1"></i> {{ \Carbon\Carbon::parse($day)->format('l, j F Y') }} &bull; {{ $dayPayments->count() }} receipt(s)
                            </td>
                            <td class="px-5 py-2 text-right text-xs font-bold text-gray-700">{{ number_format($dayPayments->sum('amount'), 2) }}</td>
                        </tr>
                        @foreach($dayPayments as $payment)
                            @php $icon = ['cash' => 'fa-money-bill-wave text-green-600', 'mobile_money' => 'fa-mobile-screen text-yellow-600', 'bank_transfer' => 'fa-building-columns text-blue-600', 'cheque' => 'fa-money-check text-purple-600'][$payment->payment_method] ?? 'fa-ellipsis text-gray-500'; @endphp
                            <tr class="hover:bg-[#967adc]/5">
                                <td class="whitespace-nowrap px-5 py-2.5">
                                    <a href="{{ route('fees.receipt', $payment) }}" target="_blank" class="font-mono text-xs text-[#2c8fc6] hover:underline">{{ $payment->receipt_number }}</a>
                                </td>
                                <td class="px-5 py-2.5">
                                    @if($payment->student)
                                        <a href="{{ route('fees.show', $payment->student) }}" class="font-medium text-gray-800 hover:text-[#2c8fc6]">{{ $payment->student->full_name }}</a>
                                        <span class="block text-xs text-gray-400">{{ $payment->student->classGroup->name ?? '' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-2.5 text-gray-600"><i class="fas {{ $icon }} mr-1.5 w-4 text-center"></i>{{ $payment->methodLabel() }}</td>
                                <td class="px-5 py-2.5 text-gray-500">{{ $payment->recordedBy->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-2.5 text-right font-semibold text-gray-800">{{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-gray-400"><i class="fas fa-receipt mb-2 block text-3xl text-gray-300"></i> No payments in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
window.addEventListener('load', function () {
    if (typeof Chart === 'undefined') return;
    const palette = @json($palette);
    const cedi = v => 'GH₵ ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = { callbacks: { label: c => (c.dataset.label ? c.dataset.label + ': ' : c.label + ': ') + cedi(c.raw) } };

    const rows = @json($rows->values());
    const classCanvas = document.getElementById('classChart');
    if (classCanvas) new Chart(classCanvas, {
        type: 'bar',
        data: { labels: rows.map(r => r.class), datasets: [
            { label: 'Billed', data: rows.map(r => r.billed), backgroundColor: '#42a5dc55', borderColor: '#42a5dc', borderWidth: 1, borderRadius: 3 },
            { label: 'Collected', data: rows.map(r => r.collected), backgroundColor: '#8cc152', borderRadius: 3 },
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: money, legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => 'GH₵ ' + v.toLocaleString() } }, x: { grid: { display: false } } } },
    });

    const methods = @json($methods->values());
    const methodCanvas = document.getElementById('methodChart');
    if (methodCanvas) new Chart(methodCanvas, {
        type: 'doughnut',
        data: { labels: methods.map(m => m.method), datasets: [{ data: methods.map(m => m.total), backgroundColor: ['#8cc152', '#f5922f', '#42a5dc', '#967adc', '#5b6f8f'], borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' }, tooltip: money } },
    });

    const items = @json($items->sortByDesc('total')->values());
    const itemCanvas = document.getElementById('itemChart');
    if (itemCanvas) new Chart(itemCanvas, {
        type: 'doughnut',
        data: { labels: items.map(i => i.item), datasets: [{ data: items.map(i => i.total), backgroundColor: items.map((_, i) => palette[i % palette.length]), borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false }, tooltip: money } },
    });

    const daily = @json($daily->map(fn ($d) => ['date' => $d['date']->format('Y-m-d'), 'total' => $d['total']])->values());
    const dailyCanvas = document.getElementById('dailyChart');
    if (dailyCanvas) new Chart(dailyCanvas, {
        type: 'line',
        data: { labels: daily.map(d => new Date(d.date + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' })),
            datasets: [{ label: 'Collected', data: daily.map(d => d.total), borderColor: '#967adc', backgroundColor: '#967adc22', fill: true, tension: .35, pointRadius: 4, pointBackgroundColor: '#967adc' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: money },
            scales: { y: { beginAtZero: true, ticks: { callback: v => 'GH₵ ' + v.toLocaleString() } }, x: { grid: { display: false } } } },
    });
});
</script>
@endpush
