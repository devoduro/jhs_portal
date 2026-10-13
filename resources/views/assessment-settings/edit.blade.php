<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $levelLabel }} Score Sheet</h2>
            <x-button href="{{ route('assessment-settings.index') }}" variant="secondary" icon="fas fa-arrow-left">All score sheets</x-button>
        </div>
    </x-slot>

    @php
        $rows = old('components', $rows);
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    @endphp

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if($errors->any())
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            @if($copyFrom)
                <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 text-sm">
                    Started from {{ \App\Support\FormLabel::label($copyFrom) }}'s columns. Nothing is saved until you click Save.
                </div>
            @endif

            @if($configuredLevels && count(array_diff($configuredLevels, [$level])))
                <form method="GET" class="flex items-center gap-2 text-sm text-gray-600">
                    <label>Start from another class's sheet:</label>
                    <select name="copy_from" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                        <option value="">Choose...</option>
                        @foreach(array_diff($configuredLevels, [$level]) as $other)
                            <option value="{{ $other }}" @selected($copyFrom === $other)>{{ \App\Support\FormLabel::label($other) }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <form method="POST" action="{{ route('assessment-settings.update', $level) }}" class="bg-white shadow-sm rounded-lg overflow-hidden"
                  x-data="sheetBuilder({{ Js::from(array_values($rows)) }}, {{ Js::from(array_map('strval', $markedIds)) }})">
                @csrf @method('PUT')

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left w-10">#</th>
                                <th class="px-4 py-3 text-left">Column name</th>
                                <th class="px-4 py-3 text-left w-64">Counts towards</th>
                                <th class="px-4 py-3 text-left w-32">Out of</th>
                                <th class="px-4 py-3 w-28"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(row, index) in rows" :key="row.key">
                                <tr>
                                    <td class="px-4 py-2 text-gray-400" x-text="index + 1"></td>
                                    <td class="px-4 py-2">
                                        <input type="hidden" :name="`components[${index}][id]`" :value="row.id ?? ''">
                                        <input type="text" :name="`components[${index}][label]`" x-model="row.label" maxlength="60" placeholder="e.g. Class Test"
                                               class="w-full rounded-md border-gray-300 text-sm">
                                    </td>
                                    <td class="px-4 py-2">
                                        <select :name="`components[${index}][type]`" x-model="row.type" :disabled="isLocked(row)" class="w-full rounded-md border-gray-300 text-sm">
                                            <option value="class">Class score (continuous assessment)</option>
                                            <option value="exam">Exam score</option>
                                        </select>
                                        <template x-if="isLocked(row)"><input type="hidden" :name="`components[${index}][type]`" :value="row.type"></template>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" :name="`components[${index}][max_mark]`" x-model="row.max_mark" step="0.5" min="0.5" max="100"
                                               class="w-full rounded-md border-gray-300 text-sm">
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <button type="button" @click="move(index, -1)" class="text-gray-400 hover:text-gray-700 px-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                                        <button type="button" @click="move(index, 1)" class="text-gray-400 hover:text-gray-700 px-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                                        <template x-if="isLocked(row)"><span class="text-xs text-gray-400 pl-1" title="Marks already entered - can be renamed but not removed"><i class="fas fa-lock"></i></span></template>
                                        <template x-if="!isLocked(row)"><button type="button" @click="remove(index)" class="text-red-600 hover:text-red-800 pl-1" title="Remove"><i class="fas fa-trash"></i></button></template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-right text-sm text-gray-600">
                                    Class score <strong x-text="fmt(classTotal)"></strong> + Exam score <strong x-text="fmt(examTotal)"></strong> =
                                </td>
                                <td class="px-4 py-3 font-bold" :class="total === 100 ? 'text-green-600' : 'text-red-600'" colspan="2">
                                    <span x-text="fmt(total)"></span> / 100
                                    <span x-show="total === 100"><i class="fas fa-check-circle"></i></span>
                                    <span x-show="total !== 100" class="text-xs font-normal">must add up to 100</span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="p-4 border-t flex flex-wrap items-center justify-between gap-3">
                    <button type="button" @click="add()" class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                        <i class="fas fa-plus"></i> Add column
                    </button>
                </div>

                <div class="p-4 border-t bg-gray-50">
                    <p class="text-sm font-medium text-gray-700 mb-2">Use this same sheet for other classes too <span class="font-normal text-gray-400">(optional)</span></p>
                    <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                        @foreach($otherLevels as $otherLevel => $otherLabel)
                            <label class="flex items-center gap-2 p-2 border rounded-lg text-sm bg-white">
                                <input type="checkbox" name="apply_to[]" value="{{ $otherLevel }}" class="rounded border-gray-300" @checked(in_array($otherLevel, (array) old('apply_to', [])))>
                                {{ $otherLabel }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Marks already entered in earlier terms keep their totals; changing a sheet never rescales them.</p>
                </div>

                <div class="p-4 border-t flex justify-end">
                    <x-button type="submit" icon="fas fa-save" x-bind:disabled="total !== 100">Save score sheet</x-button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function sheetBuilder(initialRows, markedIds) {
            var nextKey = 0;
            return {
                markedIds: markedIds,
                rows: initialRows.map(function (r) { return { key: nextKey++, id: r.id || null, label: r.label || '', type: r.type || 'class', max_mark: r.max_mark || '' }; }),
                get classTotal() { return this.sum('class'); },
                get examTotal() { return this.sum('exam'); },
                get total() { return Math.round((this.classTotal + this.examTotal) * 100) / 100; },
                sum: function (type) { return this.rows.filter(function (r) { return r.type === type; }).reduce(function (s, r) { return s + (parseFloat(r.max_mark) || 0); }, 0); },
                fmt: function (n) { return (Math.round(n * 100) / 100).toString(); },
                isLocked: function (row) { return row.id !== null && this.markedIds.indexOf(String(row.id)) !== -1; },
                add: function () { this.rows.push({ key: nextKey++, id: null, label: '', type: 'class', max_mark: '' }); },
                remove: function (i) { if (this.isLocked(this.rows[i])) return; this.rows.splice(i, 1); if (!this.rows.length) this.add(); },
                move: function (i, d) { var j = i + d; if (j < 0 || j >= this.rows.length) return; var t = this.rows[i]; this.rows[i] = this.rows[j]; this.rows[j] = t; this.rows = this.rows.slice(); },
            };
        }
    </script>
    @endpush
</x-app-layout>
