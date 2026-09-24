<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Term Fee Schedule') }}
            </h2>
            <div class="flex flex-wrap gap-2">
                <x-button href="{{ route('fees.charges.index') }}" variant="secondary" icon="fas fa-user-tag">
                    {{ __('Bill Individual Pupils') }}
                </x-button>
                @if($term)
                    <x-button href="{{ route('fees.billing', ['semester_id' => $term->id]) }}" variant="success" icon="fas fa-file-invoice">
                        {{ __('Bill Pupils for this Term') }}
                    </x-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if($terms->isEmpty())
                <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700">
                    No terms have been created yet. Create the academic year and its terms under <a href="{{ route('semesters.index') }}" class="underline">Terms</a> first.
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-6 flex flex-wrap items-end gap-6 justify-between">
                    <form method="GET" action="{{ route('fee-structures.index') }}">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                        <select name="semester_id" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-200">
                            @foreach($terms as $t)
                                <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}{{ $t->is_current ? ' - current' : '' }}</option>
                            @endforeach
                        </select>
                    </form>

                    <form method="POST" action="{{ route('fee-structures.copy') }}" class="flex items-end gap-2" onsubmit="return confirm('Copy the fees from the chosen term into {{ $term?->full_name }}? Amounts already set here will be overwritten.')">
                        @csrf
                        <input type="hidden" name="semester_id" value="{{ $term?->id }}">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Copy fees from</label>
                            <select name="from_semester_id" class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-200" required>
                                <option value="">Choose a term...</option>
                                @foreach($terms as $t)
                                    @continue($term && $t->id === $term->id)
                                    <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-button type="submit" variant="outline-primary" icon="fas fa-copy">Copy</x-button>
                    </form>

                    <div class="text-sm text-gray-600">
                        <span class="font-semibold text-gray-900">{{ $billedCount }}</span> pupil(s) billed for this term so far
                    </div>
                </div>

                <form method="POST" action="{{ route('fee-structures.save') }}" class="bg-white shadow-sm rounded-lg">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $term->id }}">

                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Fees for {{ $term->full_name }} (GH₵)</h3>
                        <p class="text-sm text-gray-500 mt-1">Enter what each class pays for each item this term. Leave a cell blank if the item doesn't apply to that class. Optional items a few pupils take (e.g. bus fee) are better billed per pupil under <em>Bill Individual Pupils</em>.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="sticky left-0 bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[12rem]">Fee Item</th>
                                    @foreach($levels as $level => $label)
                                        <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($categories as $slug => $name)
                                    <tr class="hover:bg-gray-50">
                                        <td class="sticky left-0 bg-white px-4 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $name }}</td>
                                        @foreach($levels as $level => $label)
                                            <td class="px-1 py-1">
                                                <input type="number" step="0.01" min="0" name="amounts[{{ $slug }}][{{ $level }}]"
                                                       value="{{ isset($amounts[$slug][$level]) ? number_format($amounts[$slug][$level], 2, '.', '') : '' }}"
                                                       class="fee-cell w-24 rounded border-gray-300 text-right text-sm focus:border-primary-500 focus:ring-primary-200"
                                                       data-level="{{ $level }}">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold">
                                <tr>
                                    <td class="sticky left-0 bg-gray-50 px-4 py-3 text-gray-900">Total per pupil</td>
                                    @foreach($levels as $level => $label)
                                        <td class="px-2 py-3 text-right text-gray-900 whitespace-nowrap" data-total-level="{{ $level }}">{{ number_format($totals[$level], 2) }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="p-6 flex justify-end">
                        <x-button type="submit" icon="fas fa-save">Save Fee Schedule</x-button>
                    </div>
                </form>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Fee items</h3>
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach(\App\Models\FeeCategory::orderBy('name')->get() as $category)
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 text-sm text-gray-700">
                                {{ $category->name }}
                                @unless($category->is_protected)
                                    <form method="POST" action="{{ route('fees.categories.destroy', $category) }}" onsubmit="return confirm('Remove {{ $category->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-600" title="Remove"><i class="fas fa-times"></i></button>
                                    </form>
                                @endunless
                            </span>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('fees.categories.store') }}" class="flex items-end gap-2">
                        @csrf
                        <x-input name="name" label="Add a fee item" placeholder="e.g. Lunch, Excursion, Printing" />
                        <x-button type="submit" variant="secondary" icon="fas fa-plus">Add</x-button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.querySelectorAll('.fee-cell').forEach(function (input) {
            input.addEventListener('input', function () {
                var level = input.dataset.level, total = 0;
                document.querySelectorAll('.fee-cell[data-level="' + level + '"]').forEach(function (cell) {
                    total += parseFloat(cell.value) || 0;
                });
                document.querySelector('[data-total-level="' + level + '"]').textContent =
                    total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            });
        });
    </script>
</x-app-layout>
