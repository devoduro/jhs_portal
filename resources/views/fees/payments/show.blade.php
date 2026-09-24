<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Fee Account') }} — {{ $student->full_name }}
            </h2>
            <div class="flex gap-2">
                <x-button href="{{ route('fees.print', $student) }}" variant="secondary" icon="fas fa-print" target="_blank">
                    {{ __('Print Statement') }}
                </x-button>
                <x-button href="{{ route('fees.index', ['semester_id' => $term?->id]) }}" variant="secondary" icon="fas fa-arrow-left">
                    {{ __('Pupil Fees') }}
                </x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('receipt_url'))
                <div class="p-4 bg-green-50 border border-green-200 rounded-lg flex flex-wrap justify-between items-center gap-3">
                    <p class="text-green-800 text-sm"><i class="fas fa-check-circle mr-1"></i> Payment saved. Print the receipt for the parent.</p>
                    <a href="{{ session('receipt_url') }}" target="_blank" class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-white text-sm hover:bg-green-700">
                        <i class="fas fa-receipt mr-2"></i> Print Receipt
                    </a>
                </div>
            @endif

            <!-- Pupil + term -->
            <div class="bg-white shadow-sm rounded-lg p-6 flex flex-wrap justify-between gap-4">
                <div class="flex items-center gap-4">
                    <x-student-photo :student="$student" class="w-16 h-16 rounded-full border border-gray-200" />
                    <div>
                        <p class="text-lg font-semibold text-gray-900">{{ $student->full_name }}</p>
                        <p class="text-sm text-gray-500">{{ $student->index_number }} · {{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) }}</p>
                        <p class="text-sm text-gray-500">Parent/Guardian: {{ $student->emergency_contact_name ?: '—' }} {{ $student->emergency_contact_phone ? '(' . $student->emergency_contact_phone . ')' : '' }}</p>
                    </div>
                </div>
                <form method="GET" action="{{ route('fees.show', $student) }}">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                    <select name="semester_id" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Balance brought forward</p>
                    <p class="text-xl font-bold {{ $broughtForward > 0 ? 'text-red-600' : 'text-gray-900' }}">GH₵ {{ number_format($broughtForward, 2) }}</p>
                    @if($broughtForward < 0)<p class="text-xs text-blue-600">Credit from earlier terms</p>@endif
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">{{ $term?->name ?? 'Term' }} bill</p>
                    <p class="text-xl font-bold text-gray-900">GH₵ {{ number_format($termBill, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Paid this term</p>
                    <p class="text-xl font-bold text-green-600">GH₵ {{ number_format($termPaid, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 border-2 {{ $balanceDue > 0 ? 'border-red-300' : 'border-green-300' }}">
                    <p class="text-xs uppercase text-gray-500">Amount owing now</p>
                    <p class="text-2xl font-bold {{ $balanceDue > 0 ? 'text-red-600' : 'text-green-600' }}">GH₵ {{ number_format($balanceDue, 2) }}</p>
                    @if($balanceDue < 0)<p class="text-xs text-blue-600">In credit</p>@endif
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Record Payment -->
                <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fas fa-cash-register text-primary-600 mr-2"></i>Receive Payment</h3>
                    @if(!$term)
                        <p class="text-sm text-yellow-700">Create a term first.</p>
                    @else
                        <form method="POST" action="{{ route('fees.payments.store', $student) }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">For term <span class="text-red-500">*</span></label>
                                <select name="semester_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                                    @foreach($terms as $t)
                                        <option value="{{ $t->id }}" @selected(old('semester_id', $term->id) == $t->id)>{{ $t->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-input id="amount" name="amount" type="number" label="Amount (GH₵)" min="0.01" step="0.01" :value="old('amount', $balanceDue > 0 ? number_format($balanceDue, 2, '.', '') : '')" required />
                            <x-input id="payment_date" name="payment_date" type="date" label="Date" :value="old('payment_date', date('Y-m-d'))" required />
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Paid via <span class="text-red-500">*</span></label>
                                <select name="payment_method" class="w-full rounded-lg border-gray-300 text-sm" required>
                                    @foreach(\App\Models\StudentPayment::METHODS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-input id="reference_number" name="reference_number" label="MoMo / Bank ref." placeholder="Optional" :value="old('reference_number')" />
                            <x-input id="bank" name="bank" label="Bank / Network" placeholder="e.g. MTN, GCB" :value="old('bank')" />
                            <x-input id="paid_by" name="paid_by" label="Paid by" :placeholder="$student->emergency_contact_name ?: 'Name of payer'" :value="old('paid_by')" />
                            <x-input id="notes" name="notes" label="Notes" placeholder="Optional" :value="old('notes')" class="md:col-span-2" />
                            <div class="md:col-span-3 flex flex-wrap justify-between items-center gap-3">
                                @can('send-sms')
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="send_sms" value="1" class="rounded border-gray-300" @checked($student->emergency_contact_phone)>
                                        Text a receipt to the parent
                                    </label>
                                @else
                                    <span></span>
                                @endcan
                                <x-button type="submit" icon="fas fa-check">Save Payment</x-button>
                            </div>
                            @if($errors->any())
                                <div class="md:col-span-3 p-3 bg-red-50 text-red-700 text-sm rounded">{{ $errors->first() }}</div>
                            @endif
                        </form>
                    @endif
                </div>

                <!-- Term bill breakdown -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $term?->name }} Bill</h3>
                    @if($termItems->isEmpty())
                        <p class="text-sm text-gray-500">Not billed for this term yet.</p>
                    @else
                        <table class="w-full text-sm">
                            @foreach($termItems as $item)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2 text-gray-700">{{ $item->categoryLabel() }}</td>
                                    <td class="py-2 text-right text-gray-900">{{ number_format($item->amount, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-semibold">
                                <td class="py-2">Total</td>
                                <td class="py-2 text-right">{{ number_format($termBill, 2) }}</td>
                            </tr>
                        </table>
                    @endif
                    @if($term)
                        <details class="mt-4">
                            <summary class="text-sm text-primary-600 cursor-pointer">+ Add an item to this pupil's bill</summary>
                            <form method="POST" action="{{ route('fees.charges.store', $student) }}" class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="semester_id" value="{{ $term->id }}">
                                <select name="category" class="w-full rounded-lg border-gray-300 text-sm" required>
                                    @foreach(\App\Models\FeeCategory::options() as $slug => $name)
                                        <option value="{{ $slug }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.01" name="amount" placeholder="Amount (negative for a discount)" class="w-full rounded-lg border-gray-300 text-sm" required>
                                <input type="text" name="notes" placeholder="Note, e.g. Bus - Kasoa route" class="w-full rounded-lg border-gray-300 text-sm">
                                <x-button type="submit" size="sm" fullWidth>Add to {{ $term->name }} bill</x-button>
                            </form>
                        </details>
                    @endif
                    @if($unbilledItems->isNotEmpty())
                        <div class="mt-4 p-3 bg-yellow-50 rounded text-xs text-yellow-800">
                            Fees set for {{ \App\Support\FormLabel::label($student->level) }} but not yet billed to this pupil:
                            {{ $unbilledItems->map(fn ($i) => $i->categoryLabel())->implode(', ') }}.
                            <form method="POST" action="{{ route('fees.billing.store') }}" class="mt-2">
                                @csrf
                                <input type="hidden" name="semester_id" value="{{ $term->id }}">
                                <input type="hidden" name="student_id" value="{{ $student->id }}">
                                <button type="submit" class="underline font-semibold">Bill this pupil now</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Statement -->
            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Statement of Account</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bill (Dr)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid (Cr)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($ledger as $row)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-gray-900">{{ $row['description'] }} <span class="text-xs text-gray-400">{{ $row['academic_year'] }}</span></td>
                                    <td class="px-4 py-3 text-gray-500">{{ $row['payment_mode'] ?? '' }}{{ !empty($row['reference_number']) ? ' · ' . $row['reference_number'] : '' }}</td>
                                    <td class="px-4 py-3 text-right text-red-600">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
                                    <td class="px-4 py-3 text-right text-green-600">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900">{{ number_format($row['balance'], 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @if($row['payment_id'] ?? null)
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
                                                            <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                                                            <select name="semester_id" class="w-full rounded-lg border-gray-300" required>
                                                                @foreach($terms as $t)
                                                                    <option value="{{ $t->id }}" @selected(($row['semester_id'] ?? null) == $t->id)>{{ $t->full_name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <x-input :id="'amount-' . $row['payment_id']" name="amount" type="number" label="Amount" min="0.01" step="0.01" :value="$row['credit']" required />
                                                        <div>
                                                            <label class="block text-sm font-medium text-gray-700 mb-1">Paid via</label>
                                                            <select name="payment_method" class="w-full rounded-lg border-gray-300" required>
                                                                @foreach(\App\Models\StudentPayment::METHODS as $value => $label)
                                                                    <option value="{{ $value }}" @selected($row['payment_mode'] === $label)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <x-input :id="'payment_date-' . $row['payment_id']" name="payment_date" type="date" label="Date" :value="\Illuminate\Support\Carbon::parse($row['date'])->format('Y-m-d')" required />
                                                        <x-input :id="'reference_number-' . $row['payment_id']" name="reference_number" label="Reference" :value="$row['reference_number']" />
                                                        <x-input :id="'bank-' . $row['payment_id']" name="bank" label="Bank / Network" :value="$row['bank']" />
                                                        @if($errors->getBag('edit_payment_' . $row['payment_id'])->any())
                                                            <div class="p-3 bg-red-50 text-red-700 text-sm">{{ $errors->getBag('edit_payment_' . $row['payment_id'])->first() }}</div>
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
                                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No bills or payments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
