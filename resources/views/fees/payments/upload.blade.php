<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Upload Payments') }}</h2>
            <div class="flex gap-2">
                <x-button href="{{ route('fees.collect') }}" variant="secondary" icon="fas fa-table-list">Class payment sheet</x-button>
                <x-button href="{{ route('fees.index') }}" variant="secondary" icon="fas fa-arrow-left">Pupil fees</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm space-y-2">
                    <p>Record many payments at once from a bank statement, a MoMo statement or your own list. Columns:</p>
                    <p><strong>index_number</strong> (admission number), <strong>amount</strong> and <strong>date</strong> are required.
                        <strong>method</strong> (cash, mobile money, bank, cheque), <strong>bank</strong> (e.g. MTN, GCB), <strong>reference</strong> (the transaction ID), <strong>paid_by</strong> and <strong>term</strong> are optional.</p>
                    <p>If a <strong>reference</strong> is already on that pupil's account the row is skipped, so uploading the same statement twice is safe.
                        With no term given, the payment goes to the term the date falls in.</p>
                    <a href="{{ route('fees.payments.template') }}" class="inline-flex items-center gap-1 text-blue-700 underline"><i class="fas fa-download"></i> Download template</a>
                </div>

                @if($errors->any())
                    <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('fees.payments.import') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="w-full rounded-lg border-gray-300 text-sm">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300" checked>
                        Check the file first - show what would be recorded without saving anything
                    </label>
                    <div class="flex justify-end"><x-button type="submit" icon="fas fa-upload">Upload</x-button></div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <form method="GET" class="p-4 border-b flex flex-wrap items-center gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search pupil..." class="rounded-md border-gray-300 text-sm">
                    <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="uploaded" value="1" @checked(request('uploaded')) onchange="this.form.submit()" class="rounded border-gray-300"> Uploaded ones only</label>
                    <x-button type="submit" variant="secondary" size="sm">Filter</x-button>
                </form>

                <form method="POST" action="{{ route('fees.payments.bulk-destroy') }}" onsubmit="return confirm('Remove the ticked payments? This takes the money off the pupils\' accounts.')">
                    @csrf
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 w-10"><input type="checkbox" onclick="document.querySelectorAll('.pay-box').forEach(b => b.checked = this.checked)" class="rounded border-gray-300"></th>
                                <th class="px-4 py-2 text-left">Receipt</th>
                                <th class="px-4 py-2 text-left">Pupil</th>
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-left">Method</th>
                                <th class="px-4 py-2 text-left">Reference</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($payments as $payment)
                                <tr>
                                    <td class="px-4 py-2"><input type="checkbox" name="payment_ids[]" value="{{ $payment->id }}" class="pay-box rounded border-gray-300"></td>
                                    <td class="px-4 py-2"><a href="{{ route('fees.receipt', $payment) }}" class="text-primary-600 hover:underline">{{ $payment->receipt_number }}</a></td>
                                    <td class="px-4 py-2">{{ $payment->student->full_name ?? '—' }} <span class="text-xs text-gray-400">{{ $payment->student->classGroup->name ?? '' }}</span></td>
                                    <td class="px-4 py-2 text-gray-600">{{ $payment->payment_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $payment->methodLabel() }}{{ $payment->bank ? ' · ' . $payment->bank : '' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $payment->reference_number }}</td>
                                    <td class="px-4 py-2 text-right font-medium">{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No payments found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if($payments->isNotEmpty())
                        <div class="p-4 border-t flex justify-between items-center">
                            <x-button type="submit" variant="outline-danger" size="sm" icon="fas fa-trash">Remove ticked (e.g. after a bad upload)</x-button>
                            <div>{{ $payments->links() }}</div>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
