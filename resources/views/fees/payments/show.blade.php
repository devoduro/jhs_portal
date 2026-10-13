@extends('components.app-layout')

@section('title', 'Fee Account')
@section('subtitle', $student->full_name)

@section('content')
@php
    $money = fn ($v) => 'GH₵ ' . number_format((float) $v, 2);
    $dueThisTerm = max(0, $termBill + max(0, $broughtForward));
    $paidPct = $dueThisTerm > 0 ? min(100, round($termPaid / $dueThisTerm * 100)) : ($balanceDue <= 0 ? 100 : 0);
    $status = $balanceDue > 0.009 ? ['Owing', 'bg-red-500/20 text-red-100 ring-red-300/30'] : ($balanceDue < -0.009 ? ['In credit', 'bg-sky-500/20 text-sky-100 ring-sky-300/30'] : ['Fully paid', 'bg-green-500/20 text-green-100 ring-green-300/30']);
    $input = 'w-full rounded-lg border-gray-300 text-sm focus:border-[#42a5dc] focus:ring-[#42a5dc]';
@endphp

<div class="space-y-6">
    <!-- Hero -->
    <x-dashboard-hero :title="$student->full_name" :eyebrow="$student->index_number . ' • ' . ($student->classGroup->name ?? \App\Support\FormLabel::label($student->level))"
        :subtitle="'Parent/guardian: ' . ($student->emergency_contact_name ?: 'not recorded') . ($student->emergency_contact_phone ? ' • ' . $student->emergency_contact_phone : '')">
        <x-slot:chips>
            <span class="!ring-1 {{ $status[1] }}"><i class="fas fa-circle text-[8px]"></i> {{ $status[0] }}</span>
            @if($term)<span><i class="fas fa-book-open"></i> {{ $term->full_name }}</span>@endif
            <span><i class="fas fa-chart-pie"></i> {{ $paidPct }}% of this term paid</span>
        </x-slot:chips>
        <a class="primary" href="{{ route('fees.print', $student) }}" target="_blank"><i class="fas fa-print text-[#42a5dc]"></i> Print Statement</a>
        @can('manage-students')
            <a href="{{ route('students.show', $student) }}"><i class="fas fa-user"></i> Profile</a>
        @endcan
        <a href="{{ route('fees.index', ['semester_id' => $term?->id]) }}"><i class="fas fa-arrow-left"></i> All pupils</a>
    </x-dashboard-hero>

    @if(session('receipt_url'))
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-green-50 p-4 ring-1 ring-green-200">
            <p class="text-sm text-green-800"><i class="fas fa-circle-check mr-1"></i> Payment saved. Print the receipt for the parent.</p>
            <a href="{{ session('receipt_url') }}" target="_blank" class="inline-flex items-center rounded-md bg-[#8cc152] px-4 py-2 text-sm font-semibold text-white hover:bg-[#74a83b]">
                <i class="fas fa-receipt mr-2"></i> Print Receipt
            </a>
        </div>
    @endif

    <!-- Term switcher + progress -->
    <div class="flex flex-col gap-4 rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100 md:flex-row md:items-center">
        <form method="GET" action="{{ route('fees.show', $student) }}" class="flex items-center gap-2">
            <label class="text-sm font-semibold text-gray-700"><i class="fas fa-calendar-days mr-1 text-[#42a5dc]"></i> Term</label>
            <select name="semester_id" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                @foreach($terms as $t)
                    <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                @endforeach
            </select>
        </form>
        <div class="flex-1">
            <div class="mb-1 flex justify-between text-xs text-gray-500">
                <span>Paid this term: <strong class="text-gray-700">{{ $money($termPaid) }}</strong> of {{ $money($dueThisTerm) }} due</span>
                <span class="font-semibold text-gray-700">{{ $paidPct }}%</span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                <div class="h-3 rounded-full transition-all {{ $paidPct >= 100 ? 'bg-[#8cc152]' : ($paidPct >= 50 ? 'bg-[#42a5dc]' : 'bg-[#f5922f]') }}" style="width: {{ $paidPct }}%"></div>
            </div>
        </div>
    </div>

    <!-- Tiles -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile color="slate" icon="fa-clock-rotate-left" title="Brought Forward"
            :sub="$broughtForward < 0 ? 'Credit from earlier terms' : 'From earlier terms'" :value="$money(abs($broughtForward))" />
        <x-stat-tile color="blue" icon="fa-file-invoice" :title="($term?->name ?? 'Term') . ' Bill'"
            :sub="$termItems->count() . ' item(s) billed'" :value="$money($termBill)" />
        <x-stat-tile color="green" icon="fa-coins" title="Paid This Term"
            :sub="$ledger ? collect($ledger)->whereNotNull('payment_id')->where('semester_id', $term?->id)->count() . ' payment(s)' : 'No payments yet'" :value="$money($termPaid)" />
        <x-stat-tile :color="$balanceDue > 0.009 ? 'red' : ($balanceDue < -0.009 ? 'purple' : 'green')" :icon="$balanceDue > 0.009 ? 'fa-circle-exclamation' : 'fa-circle-check'"
            title="Owing Now" :sub="$balanceDue > 0.009 ? 'Balance to collect' : ($balanceDue < -0.009 ? 'Pupil is in credit' : 'Nothing owed')"
            :value="$money(abs($balanceDue))" :badge="$balanceDue > 0.009 ? $paidPct . '% paid' : null" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Receive payment -->
        <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100 lg:col-span-2">
            <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-[#42a5dc]/10 to-transparent px-5 py-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-md bg-[#42a5dc] text-white"><i class="fas fa-cash-register"></i></span>
                <div>
                    <h3 class="font-semibold text-gray-900">Receive payment</h3>
                    <p class="text-xs text-gray-500">A numbered receipt is created when you save.</p>
                </div>
            </div>
            @if(!$term)
                <p class="p-5 text-sm text-yellow-700">Create a term first.</p>
            @else
                <form method="POST" action="{{ route('fees.payments.store', $student) }}" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-3">
                    @csrf
                    <div class="md:col-span-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Amount (GH₵) <span class="text-red-500">*</span></label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <input id="amount" name="amount" type="number" min="0.01" step="0.01" required
                                   value="{{ old('amount', $balanceDue > 0 ? number_format($balanceDue, 2, '.', '') : '') }}"
                                   class="{{ $input }} text-lg font-semibold sm:max-w-[14rem]">
                            <div class="flex flex-wrap gap-2">
                                @if($balanceDue > 0)
                                    <button type="button" data-amount="{{ number_format($balanceDue, 2, '.', '') }}" class="quick-amount rounded-full bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Full balance {{ number_format($balanceDue, 2) }}</button>
                                    <button type="button" data-amount="{{ number_format($balanceDue / 2, 2, '.', '') }}" class="quick-amount rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">Half {{ number_format($balanceDue / 2, 2) }}</button>
                                @endif
                                @if($termBill > 0 && abs($termBill - $balanceDue) > 0.009)
                                    <button type="button" data-amount="{{ number_format($termBill, 2, '.', '') }}" class="quick-amount rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Term bill {{ number_format($termBill, 2) }}</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">For term</label>
                        <select name="semester_id" class="{{ $input }}" required>
                            @foreach($terms as $t)
                                <option value="{{ $t->id }}" @selected(old('semester_id', $term->id) == $t->id)>{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Date</label>
                        <input name="payment_date" type="date" required value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Paid by</label>
                        <input name="paid_by" type="text" value="{{ old('paid_by') }}" placeholder="{{ $student->emergency_contact_name ?: 'Name of payer' }}" class="{{ $input }}">
                    </div>

                    <div class="md:col-span-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Paid via</label>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                            @foreach(['cash' => 'fa-money-bill-wave', 'mobile_money' => 'fa-mobile-screen', 'bank_transfer' => 'fa-building-columns', 'cheque' => 'fa-money-check', 'other' => 'fa-ellipsis'] as $value => $icon)
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="{{ $value }}" class="peer sr-only" @checked(old('payment_method', 'cash') === $value)>
                                    <span class="flex flex-col items-center gap-1 rounded-md border border-gray-200 px-2 py-2.5 text-xs font-medium text-gray-600 transition peer-checked:border-[#42a5dc] peer-checked:bg-[#42a5dc]/10 peer-checked:text-[#2c8fc6] hover:bg-gray-50">
                                        <i class="fas {{ $icon }} text-base"></i> {{ \App\Models\StudentPayment::METHODS[$value] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">MoMo / bank ref.</label>
                        <input name="reference_number" type="text" value="{{ old('reference_number') }}" placeholder="Optional" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Bank / network</label>
                        <input name="bank" type="text" value="{{ old('bank') }}" placeholder="e.g. MTN, GCB" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                        <input name="notes" type="text" value="{{ old('notes') }}" placeholder="Optional" class="{{ $input }}">
                    </div>

                    @if($errors->any())
                        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 md:col-span-3">{{ $errors->first() }}</div>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 md:col-span-3">
                        @can('send-sms')
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="send_sms" value="1" class="rounded border-gray-300 text-[#42a5dc]" @checked($student->emergency_contact_phone) @disabled(!$student->emergency_contact_phone)>
                                <i class="fas fa-sms text-[#42a5dc]"></i> Text a receipt to the parent
                                @unless($student->emergency_contact_phone)<span class="text-xs text-gray-400">(no phone on file)</span>@endunless
                            </label>
                        @else
                            <span></span>
                        @endcan
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-[#8cc152] px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#74a83b]">
                            <i class="fas fa-check"></i> Save payment
                        </button>
                    </div>
                </form>
            @endif
        </div>

        @can('send-sms')
            @if($term)
                <form method="POST" action="{{ route('parent-notices.send') }}" class="flex flex-wrap items-center gap-4 rounded-md bg-white px-5 py-3 shadow-sm ring-1 ring-gray-100">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $term->id }}">
                    <input type="hidden" name="student_ids[]" value="{{ $student->id }}">
                    <input type="hidden" name="kinds[]" value="fees">
                    <span class="text-sm font-medium text-gray-700"><i class="fas fa-paper-plane text-[#42a5dc]"></i> Send {{ $term->name }} fee statement to parent</span>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600"><input type="checkbox" name="channels[]" value="sms" class="rounded border-gray-300" @checked($student->emergency_contact_phone) @disabled(!$student->emergency_contact_phone)> SMS</label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600"><input type="checkbox" name="channels[]" value="email" class="rounded border-gray-300" @checked($student->parent_email) @disabled(!$student->parent_email)> Email</label>
                    @unless($student->emergency_contact_phone || $student->parent_email)<span class="text-xs text-gray-400">No parent phone or email on file.</span>@endunless
                    <button type="submit" class="ml-auto rounded-md border border-[#42a5dc] px-3 py-1.5 text-sm font-semibold text-[#2c8fc6] hover:bg-[#42a5dc]/10">Send</button>
                </form>
            @endif
        @endcan

        <!-- Term bill -->
        <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
            <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-[#967adc]/10 to-transparent px-5 py-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-md bg-[#967adc] text-white"><i class="fas fa-list-check"></i></span>
                <h3 class="font-semibold text-gray-900">{{ $term?->name }} bill</h3>
            </div>
            <div class="p-5">
                @if($termItems->isEmpty())
                    <p class="py-4 text-center text-sm text-gray-500">Not billed for this term yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach($termItems as $item)
                            <li class="flex items-center justify-between py-2">
                                <span class="flex items-center gap-2 text-gray-700"><span class="h-2 w-2 rounded-full {{ ['bg-[#42a5dc]', 'bg-[#8cc152]', 'bg-[#f5922f]', 'bg-[#967adc]', 'bg-[#5b6f8f]', 'bg-[#da4453]'][$loop->index % 6] }}"></span>{{ $item->categoryLabel() }}</span>
                                <span class="font-medium {{ $item->amount < 0 ? 'text-green-600' : 'text-gray-900' }}">{{ number_format($item->amount, 2) }}</span>
                            </li>
                        @endforeach
                        <li class="flex items-center justify-between pt-3 font-semibold"><span>Total</span><span>{{ $money($termBill) }}</span></li>
                    </ul>
                @endif

                @if($unbilledItems->isNotEmpty())
                    <div class="mt-4 rounded-md bg-yellow-50 p-3 text-xs text-yellow-800 ring-1 ring-yellow-100">
                        Set for {{ \App\Support\FormLabel::label($student->level) }} but not billed yet:
                        {{ $unbilledItems->map(fn ($i) => $i->categoryLabel())->implode(', ') }}.
                        <form method="POST" action="{{ route('fees.billing.store') }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="semester_id" value="{{ $term->id }}">
                            <input type="hidden" name="student_id" value="{{ $student->id }}">
                            <button type="submit" class="font-semibold underline">Bill this pupil now</button>
                        </form>
                    </div>
                @endif

                @if($term)
                    <details class="mt-4 rounded-md bg-gray-50 p-3">
                        <summary class="cursor-pointer text-sm font-medium text-[#2c8fc6]"><i class="fas fa-plus mr-1"></i> Add an item (bus, uniform, discount…)</summary>
                        <form method="POST" action="{{ route('fees.charges.store', $student) }}" class="mt-3 space-y-2">
                            @csrf
                            <input type="hidden" name="semester_id" value="{{ $term->id }}">
                            <select name="category" class="{{ $input }}" required>
                                @foreach(\App\Models\FeeCategory::options() as $slug => $name)
                                    <option value="{{ $slug }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" name="amount" placeholder="Amount (negative = discount)" class="{{ $input }}" required>
                            <input type="text" name="notes" placeholder="Note, e.g. Bus - Kasoa route" class="{{ $input }}">
                            <button type="submit" class="w-full rounded-md bg-[#967adc] px-3 py-2 text-sm font-semibold text-white hover:bg-[#7d62c4]">Add to {{ $term->name }} bill</button>
                        </form>
                    </details>
                @endif
            </div>
        </div>
    </div>

    <!-- Statement -->
    <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-md bg-[#5b6f8f] text-white"><i class="fas fa-book"></i></span>
                <div>
                    <h3 class="font-semibold text-gray-900">Statement of account</h3>
                    <p class="text-xs text-gray-500">Every bill and payment, newest first</p>
                </div>
            </div>
            <a href="{{ route('fees.print', $student) }}" target="_blank" class="text-sm font-medium text-primary-600 hover:text-primary-700"><i class="fas fa-print mr-1"></i> Print</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Date</th>
                        <th class="px-5 py-3 text-left font-medium">Description</th>
                        <th class="px-5 py-3 text-right font-medium">Bill</th>
                        <th class="px-5 py-3 text-right font-medium">Paid</th>
                        <th class="px-5 py-3 text-right font-medium">Balance</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($ledger as $row)
                        @php $isPayment = (bool) ($row['payment_id'] ?? null); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $isPayment ? 'bg-green-100 text-green-700' : 'bg-blue-50 text-blue-600' }}">
                                        <i class="fas {{ $isPayment ? 'fa-arrow-down' : 'fa-file-invoice' }} text-xs"></i>
                                    </span>
                                    <div>
                                        <p class="font-medium text-gray-800">{{ $row['description'] }}</p>
                                        <p class="text-xs text-gray-400">{{ $row['academic_year'] }}{{ !empty($row['payment_mode']) ? ' • ' . $row['payment_mode'] : '' }}{{ !empty($row['reference_number']) ? ' • ' . $row['reference_number'] : '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right text-gray-700">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-medium text-green-600">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold {{ $row['balance'] > 0.009 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($row['balance'], 2) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                @if($isPayment)
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('fees.receipt', $row['payment_id']) }}" target="_blank" class="text-green-600 hover:text-green-800" title="Print receipt"><i class="fas fa-receipt"></i></a>
                                        <x-modal :id="'edit-payment-' . $row['payment_id']" title="Edit Payment" maxWidth="lg">
                                            <x-slot name="trigger">
                                                <button type="button" class="text-indigo-600 hover:text-indigo-900" title="Edit"><i class="fas fa-edit"></i></button>
                                            </x-slot>
                                            <form method="POST" action="{{ route('fees.payments.update', $row['payment_id']) }}" class="space-y-4 text-left">
                                                @csrf
                                                @method('PUT')
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Term</label>
                                                    <select name="semester_id" class="{{ $input }}" required>
                                                        @foreach($terms as $t)
                                                            <option value="{{ $t->id }}" @selected(($row['semester_id'] ?? null) == $t->id)>{{ $t->full_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <x-input :id="'amount-' . $row['payment_id']" name="amount" type="number" label="Amount" min="0.01" step="0.01" :value="$row['credit']" required />
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-gray-700">Paid via</label>
                                                    <select name="payment_method" class="{{ $input }}" required>
                                                        @foreach(\App\Models\StudentPayment::METHODS as $value => $label)
                                                            <option value="{{ $value }}" @selected($row['payment_mode'] === $label)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <x-input :id="'payment_date-' . $row['payment_id']" name="payment_date" type="date" label="Date" :value="\Illuminate\Support\Carbon::parse($row['date'])->format('Y-m-d')" required />
                                                <x-input :id="'reference_number-' . $row['payment_id']" name="reference_number" label="Reference" :value="$row['reference_number']" />
                                                <x-input :id="'bank-' . $row['payment_id']" name="bank" label="Bank / Network" :value="$row['bank']" />
                                                @if($errors->getBag('edit_payment_' . $row['payment_id'])->any())
                                                    <div class="bg-red-50 p-3 text-sm text-red-700">{{ $errors->getBag('edit_payment_' . $row['payment_id'])->first() }}</div>
                                                @endif
                                                <div class="flex justify-end"><x-button type="submit">Save Changes</x-button></div>
                                            </form>
                                        </x-modal>
                                        <form action="{{ route('fees.payments.destroy', $row['payment_id']) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Delete payment {{ $row['receipt_number'] }}? This cannot be undone.')"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400"><i class="fas fa-receipt mb-2 block text-3xl text-gray-300"></i> No bills or payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.quick-amount').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById('amount');
            input.value = btn.dataset.amount;
            input.focus();
        });
    });
</script>
@endsection
