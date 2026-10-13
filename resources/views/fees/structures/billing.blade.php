<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Bill Pupils for a Term') }}</h2>
            <x-button href="{{ route('fee-structures.index', ['semester_id' => $term?->id]) }}" variant="secondary" icon="fas fa-arrow-left">
                {{ __('Fee Schedule') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm">
                Billing adds the term's fees (from the fee schedule) to each pupil's account according to their class.
                Pupils already billed for an item this term are skipped, so it is safe to run again after admitting new pupils.
            </div>

            <form method="GET" action="{{ route('fees.billing') }}" class="bg-white shadow-sm rounded-lg p-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                <select name="semester_id" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-200">
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}{{ $t->is_current ? ' - current' : '' }}</option>
                    @endforeach
                </select>
            </form>

            @if($term && $plan && $plan['pupils'] > 0)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b flex flex-wrap justify-between items-center gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">What billing would do right now</h3>
                            <p class="text-xs text-gray-500">{{ $plan['new_items'] }} new bill item(s) worth GH₵ {{ number_format($plan['new_total'], 2) }} across all classes.
                                @if($plan['no_fees']) {{ $plan['no_fees'] }} pupil(s) are in a class level with no fees set for this term. @endif</p>
                        </div>
                        <div class="flex gap-2">
                            <x-button href="{{ route('fees.bulk-bill', ['semester_id' => $term->id]) }}" variant="secondary" size="sm" icon="fas fa-layer-group">Bill one item to many pupils</x-button>
                        </div>
                    </div>
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr><th class="px-6 py-2 text-left">Class</th><th class="px-6 py-2 text-right">Pupils</th><th class="px-6 py-2 text-right">Still to bill</th><th class="px-6 py-2 text-right">New items</th><th class="px-6 py-2 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($plan['rows'] as $row)
                                <tr>
                                    <td class="px-6 py-2">{{ $row['class'] }}</td>
                                    <td class="px-6 py-2 text-right text-gray-600">{{ $row['pupils'] }}</td>
                                    <td class="px-6 py-2 text-right {{ $row['to_bill'] ? 'font-medium text-orange-600' : 'text-green-600' }}">{{ $row['to_bill'] ?: 'All billed' }}</td>
                                    <td class="px-6 py-2 text-right text-gray-600">{{ $row['items'] ?: '—' }}</td>
                                    <td class="px-6 py-2 text-right">{{ $row['amount'] ? number_format($row['amount'], 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($term)
                <form method="POST" action="{{ route('fees.billing.store') }}" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $term->id }}">

                    @if(empty($categories))
                        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 text-sm">
                            No fees are set for {{ $term->full_name }} yet.
                            <a href="{{ route('fee-structures.index', ['semester_id' => $term->id]) }}" class="underline">Set the fee schedule</a> first.
                        </div>
                    @else
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="text-sm font-semibold text-gray-900">Classes to bill</h3>
                                <label class="text-xs text-gray-500"><input type="checkbox" id="all-classes" checked class="rounded border-gray-300"> All classes</label>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @foreach($classGroups as $classGroup)
                                    <label class="flex items-center gap-2 p-2 border rounded-lg text-sm">
                                        <input type="checkbox" name="class_group_ids[]" value="{{ $classGroup->id }}" checked class="class-box rounded border-gray-300">
                                        <span>{{ $classGroup->name }} <span class="text-gray-400">({{ $classGroup->students_count }})</span></span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Pupils not yet placed in a class are billed only when all classes are selected (by their class level).</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 mb-2">Fee items to bill</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach($categories as $slug => $name)
                                    <label class="flex items-center gap-2 p-2 border rounded-lg text-sm">
                                        <input type="checkbox" name="categories[]" value="{{ $slug }}" checked class="rounded border-gray-300">
                                        {{ $name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="refresh" value="1" class="mt-1 rounded border-gray-300">
                            <span>Update pupils already billed to the current schedule amounts (use this if you corrected a fee after billing).</span>
                        </label>

                        <div class="flex justify-end">
                            <x-button type="submit" variant="success" icon="fas fa-file-invoice" onclick="return confirm('Bill the selected pupils for {{ $term->full_name }}?')">
                                Generate Bills
                            </x-button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>

    <script>
        var allBox = document.getElementById('all-classes');
        if (allBox) {
            allBox.addEventListener('change', function () {
                document.querySelectorAll('.class-box').forEach(function (box) { box.checked = allBox.checked; });
            });
        }
        // When every class is ticked, send no class filter so unplaced pupils are billed too.
        document.querySelectorAll('form[action$="billing"]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var boxes = document.querySelectorAll('.class-box');
                if (boxes.length && Array.prototype.every.call(boxes, function (b) { return b.checked; })) {
                    boxes.forEach(function (b) { b.disabled = true; });
                }
            });
        });
    </script>
</x-app-layout>
