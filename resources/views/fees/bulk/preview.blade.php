<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Review Before Billing') }}</h2>
            <x-button href="{{ route('fees.bulk-bill', ['semester_id' => $term->id]) }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Change') }}</x-button>
        </div>
    </x-slot>

    @php
        $money = fn ($v) => 'GH₵ ' . number_format((float) $v, 2);
        $labels = [
            'add' => ['Will be billed', 'bg-green-50 text-green-700'],
            'update' => ['Will be replaced', 'bg-blue-50 text-blue-700'],
            'skip' => ['Already billed - left as is', 'bg-gray-100 text-gray-600'],
            'conflict' => ['On the fee schedule - left as is', 'bg-yellow-50 text-yellow-700'],
            'nothing' => ['No term bill to take % of', 'bg-yellow-50 text-yellow-700'],
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-4"><p class="text-xs text-gray-500">Item</p><p class="font-semibold">{{ $itemName }}</p><p class="text-xs text-gray-500">{{ $term->full_name }}</p></div>
                <div class="bg-white rounded-lg shadow-sm p-4"><p class="text-xs text-gray-500">Pupils affected</p><p class="text-2xl font-bold text-gray-900">{{ $plan['count'] }}</p><p class="text-xs text-gray-500">{{ $plan['skipped'] }} left unchanged</p></div>
                <div class="bg-white rounded-lg shadow-sm p-4"><p class="text-xs text-gray-500">Total {{ $plan['total'] < 0 ? 'discount' : 'to be billed' }}</p><p class="text-2xl font-bold {{ $plan['total'] < 0 ? 'text-green-600' : 'text-gray-900' }}">{{ $money(abs($plan['total'])) }}</p></div>
            </div>

            <form method="POST" action="{{ route('fees.bulk-bill.store') }}" class="bg-white shadow-sm rounded-lg overflow-hidden">
                @csrf
                @foreach($input as $name => $value)
                    @if($value !== null && !is_array($value))
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                @foreach($classGroupIds as $id)<input type="hidden" name="class_group_ids[]" value="{{ $id }}">@endforeach
                @foreach($studentIds as $id)<input type="hidden" name="student_ids[]" value="{{ $id }}">@endforeach

                <div class="overflow-x-auto max-h-[28rem]">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left">Pupil</th>
                                <th class="px-4 py-2 text-left">Class</th>
                                @if($options['mode'] === 'percent')<th class="px-4 py-2 text-right">Bill it is based on</th>@endif
                                <th class="px-4 py-2 text-right">Already on bill</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2 text-left">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($plan['rows'] as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['student']->full_name }} <span class="text-xs text-gray-400">{{ $row['student']->index_number }}</span></td>
                                    <td class="px-4 py-2 text-gray-600">{{ $row['student']->classGroup->name ?? '—' }}</td>
                                    @if($options['mode'] === 'percent')<td class="px-4 py-2 text-right text-gray-600">{{ number_format($row['base'], 2) }}</td>@endif
                                    <td class="px-4 py-2 text-right text-gray-600">{{ $row['current'] !== null ? number_format($row['current'], 2) : '—' }}</td>
                                    <td class="px-4 py-2 text-right font-medium {{ $row['amount'] < 0 ? 'text-green-600' : '' }}">{{ number_format($row['amount'], 2) }}</td>
                                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $labels[$row['action']][1] }}">{{ $labels[$row['action']][0] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t flex justify-between items-center">
                    <p class="text-xs text-gray-500">Nothing has been saved yet.</p>
                    <x-button type="submit" variant="success" icon="fas fa-file-invoice" :disabled="$plan['count'] === 0">Confirm &amp; bill {{ $plan['count'] }} pupil(s)</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
