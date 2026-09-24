{{-- Shared by the on-screen and printed fee collection reports. --}}
@php $rate = $grand['billed'] > 0 ? round($grand['collected'] / $grand['billed'] * 100, 1) : 0; @endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-100">
        <p class="text-xs uppercase text-gray-500">Billed ({{ $term?->name }})</p>
        <p class="text-xl font-bold">GH₵ {{ number_format($grand['billed'], 2) }}</p>
    </div>
    <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-100">
        <p class="text-xs uppercase text-gray-500">Collected ({{ $term?->name }})</p>
        <p class="text-xl font-bold text-green-600">GH₵ {{ number_format($grand['collected'], 2) }}</p>
    </div>
    <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-100">
        <p class="text-xs uppercase text-gray-500">Collection rate</p>
        <p class="text-xl font-bold">{{ $rate }}%</p>
    </div>
    <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-100">
        <p class="text-xs uppercase text-gray-500">Total still owed (all terms)</p>
        <p class="text-xl font-bold text-red-600">GH₵ {{ number_format($grand['outstanding'], 2) }}</p>
    </div>
</div>

<div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
    <h3 class="px-4 py-3 font-semibold text-gray-900 border-b">By class — {{ $term?->full_name }}</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Pupils</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Owing</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Billed</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Collected</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">% Collected</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $row['class'] }}</td>
                        <td class="px-4 py-2 text-right">{{ $row['pupils'] }}</td>
                        <td class="px-4 py-2 text-right">{{ $row['debtors'] }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($row['billed'], 2) }}</td>
                        <td class="px-4 py-2 text-right text-green-700">{{ number_format($row['collected'], 2) }}</td>
                        <td class="px-4 py-2 text-right">{{ $row['percentage'] }}%</td>
                        <td class="px-4 py-2 text-right text-red-700">{{ number_format($row['outstanding'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No pupils or bills for this term.</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right">{{ $grand['pupils'] }}</td>
                        <td class="px-4 py-2 text-right">{{ $rows->sum('debtors') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($grand['billed'], 2) }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($grand['collected'], 2) }}</td>
                        <td class="px-4 py-2 text-right">{{ $rate }}%</td>
                        <td class="px-4 py-2 text-right">{{ number_format($grand['outstanding'], 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
        <h3 class="px-4 py-3 font-semibold text-gray-900 border-b">Billed by fee item</h3>
        <table class="min-w-full text-sm">
            @forelse($items as $item)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ $item['item'] }}</td>
                    <td class="px-4 py-2 text-right text-gray-500">{{ $item['pupils'] }} pupils</td>
                    <td class="px-4 py-2 text-right">{{ number_format($item['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-4 text-gray-400">Nothing billed yet.</td></tr>
            @endforelse
        </table>
    </div>
    <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
        <h3 class="px-4 py-3 font-semibold text-gray-900 border-b">Collected by payment mode</h3>
        <table class="min-w-full text-sm">
            @forelse($methods as $method)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ $method['method'] }}</td>
                    <td class="px-4 py-2 text-right text-gray-500">{{ $method['count'] }} payments</td>
                    <td class="px-4 py-2 text-right">{{ number_format($method['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-4 text-gray-400">No payments yet.</td></tr>
            @endforelse
        </table>
    </div>
</div>

<div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
    <h3 class="px-4 py-3 font-semibold text-gray-900 border-b">Cash book: {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Receipt</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pupil</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Received by</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($payments->groupBy(fn ($p) => $p->payment_date->format('Y-m-d')) as $day => $dayPayments)
                    @foreach($dayPayments as $payment)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $payment->payment_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $payment->receipt_number }}</td>
                            <td class="px-4 py-2">{{ $payment->student->full_name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $payment->student?->classGroup->name ?? '' }}</td>
                            <td class="px-4 py-2">{{ $payment->methodLabel() }}</td>
                            <td class="px-4 py-2">{{ $payment->recordedBy->name ?? '' }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-semibold">
                        <td colspan="6" class="px-4 py-2 text-right">Total for {{ \Carbon\Carbon::parse($day)->format('D d M Y') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($dayPayments->sum('amount'), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No payments in this period.</td></tr>
                @endforelse
            </tbody>
            @if($payments->isNotEmpty())
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="6" class="px-4 py-2 text-right">Period total ({{ $payments->count() }} payments)</td>
                        <td class="px-4 py-2 text-right">GH₵ {{ number_format($payments->sum('amount'), 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
