<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Class Payment Sheet') }}</h2>
            <div class="flex gap-2">
                <x-button href="{{ route('fees.payments.upload') }}" variant="secondary" icon="fas fa-file-arrow-up">Upload payments</x-button>
                <x-button href="{{ route('fees.index', ['semester_id' => $term?->id]) }}" variant="secondary" icon="fas fa-arrow-left">Pupil fees</x-button>
            </div>
        </div>
    </x-slot>

    @php $money = fn ($v) => number_format((float) $v, 2); @endphp

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm">
                Take fees for a whole class in one go: type what each pupil paid and save. Each payment gets its own receipt number and
                goes onto that pupil's account. Leave a pupil's box empty if they paid nothing.
            </div>

            @if(session('receipts'))
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Receipts just issued</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1 text-sm">
                        @foreach(session('receipts') as $receipt)
                            <a href="{{ $receipt['url'] }}" target="_blank" class="flex justify-between rounded px-3 py-1.5 hover:bg-gray-50">
                                <span>{{ $receipt['name'] }} <span class="text-xs text-gray-400">{{ $receipt['number'] }}</span></span>
                                <span class="font-medium">GH₵ {{ $money($receipt['amount']) }} <i class="fas fa-print text-gray-400 ml-1"></i></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="GET" action="{{ route('fees.collect') }}" class="bg-white shadow-sm rounded-lg p-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Term</label>
                    <select name="semester_id" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Class</label>
                    <select name="class_group_id" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                        <option value="">Choose a class...</option>
                        @foreach($classGroups as $cg)
                            <option value="{{ $cg->id }}" @selected($classGroup && $classGroup->id === $cg->id)>{{ $cg->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if($classGroup && $term)
                @if($errors->any())
                    <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('fees.collect.store') }}" class="bg-white shadow-sm rounded-lg overflow-hidden" id="sheet-form">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $term->id }}">
                    <input type="hidden" name="class_group_id" value="{{ $classGroup->id }}">

                    <div class="p-4 border-b grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date received</label>
                            <input type="date" name="payment_date" value="{{ old('payment_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="w-full rounded-md border-gray-300 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Paid by</label>
                            <select name="payment_method" class="w-full rounded-md border-gray-300 text-sm">
                                @foreach($methods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Bank / network <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="bank" value="{{ old('bank') }}" placeholder="e.g. MTN, GCB" class="w-full rounded-md border-gray-300 text-sm">
                        </div>
                        @can('send-sms')
                            <label class="flex items-center gap-2 text-sm text-gray-700 md:mt-5">
                                <input type="checkbox" name="send_sms" value="1" class="rounded border-gray-300"> Text a receipt to each parent
                            </label>
                        @endcan
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-left w-10">#</th>
                                    <th class="px-3 py-2 text-left">Pupil</th>
                                    <th class="px-3 py-2 text-right">Term bill</th>
                                    <th class="px-3 py-2 text-right">Paid this term</th>
                                    <th class="px-3 py-2 text-right">Balance</th>
                                    <th class="px-3 py-2 text-center">Amount received (GH₵)</th>
                                    <th class="px-3 py-2 text-left">Reference <span class="normal-case font-normal text-gray-400">(optional)</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($rows as $row)
                                    @php $pupil = $row['student']; @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-gray-400">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2">
                                            <a href="{{ route('fees.show', ['student' => $pupil, 'semester_id' => $term->id]) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $pupil->full_name }}</a>
                                            <div class="text-xs text-gray-500">{{ $pupil->index_number }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-600">{{ $row['term_bill'] > 0 ? $money($row['term_bill']) : '—' }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600">{{ $row['term_paid'] > 0 ? $money($row['term_paid']) : '—' }}</td>
                                        <td class="px-3 py-2 text-right font-medium {{ $row['balance'] > 0.009 ? 'text-red-600' : ($row['balance'] < -0.009 ? 'text-purple-600' : 'text-green-600') }}">
                                            {{ $money(abs($row['balance'])) }}{{ $row['balance'] < -0.009 ? ' cr' : '' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="number" step="0.01" min="0" name="rows[{{ $pupil->id }}][amount]" value="{{ old("rows.{$pupil->id}.amount") }}"
                                                   data-balance="{{ max(0, $row['balance']) }}" class="amount-input w-32 rounded border-gray-300 text-right">
                                        </td>
                                        <td class="px-3 py-2"><input type="text" name="rows[{{ $pupil->id }}][reference]" value="{{ old("rows.{$pupil->id}.reference") }}" maxlength="255" class="w-40 rounded border-gray-300 text-sm"></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-3 py-10 text-center text-gray-400">No active pupils in {{ $classGroup->name }}.</td></tr>
                                @endforelse
                            </tbody>
                            @if($rows->isNotEmpty())
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="5" class="px-3 py-3 text-right text-sm text-gray-600"><span id="paying-count">0</span> pupil(s) paying</td>
                                        <td class="px-3 py-3 text-right font-bold">GH₵ <span id="sheet-total">0.00</span></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    @if($rows->isNotEmpty())
                        <div class="p-4 border-t flex justify-between items-center">
                            <p class="text-xs text-gray-500">A box turns orange when it is more than the pupil owes (the extra stays on their account as credit).</p>
                            <x-button type="submit" variant="success" icon="fas fa-cash-register" id="save-btn">Record payments</x-button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('sheet-form');
            if (!form) return;
            var inputs = Array.prototype.slice.call(form.querySelectorAll('.amount-input'));
            function refresh() {
                var total = 0, count = 0;
                inputs.forEach(function (i) {
                    var v = parseFloat(i.value) || 0;
                    if (v > 0) { total += v; count++; }
                    i.classList.toggle('border-orange-400', v > parseFloat(i.dataset.balance) + 0.009);
                });
                document.getElementById('sheet-total').textContent = total.toFixed(2);
                document.getElementById('paying-count').textContent = count;
            }
            inputs.forEach(function (i, idx) {
                i.addEventListener('input', refresh);
                i.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); var n = inputs[idx + 1]; if (n) { n.focus(); n.select(); } }
                });
            });
            // One click only - a second click would receive the same money twice.
            form.addEventListener('submit', function (e) {
                var count = parseInt(document.getElementById('paying-count').textContent, 10);
                if (!count) { e.preventDefault(); alert('Type an amount against at least one pupil.'); return; }
                if (!confirm('Record ' + count + ' payment(s) totalling GH₵ ' + document.getElementById('sheet-total').textContent + '?')) { e.preventDefault(); return; }
                var btn = document.getElementById('save-btn'); if (btn) { btn.disabled = true; btn.classList.add('opacity-50'); }
            });
            refresh();
        })();
    </script>
</x-app-layout>
