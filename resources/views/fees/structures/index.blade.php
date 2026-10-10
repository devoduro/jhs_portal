@extends('components.app-layout')

@section('title', 'Fee Schedule')
@section('subtitle', $term ? $term->full_name : 'No term selected')

@section('content')
@php
    $money = fn ($v) => 'GH₵ ' . number_format((float) $v, 2);
    $palette = ['#42a5dc', '#8cc152', '#f5922f', '#967adc', '#5b6f8f', '#da4453', '#37bc9b', '#e8a838', '#4a89dc', '#d770ad', '#a0d468'];
    $colorFor = fn ($slug) => $palette[abs(crc32($slug)) % count($palette)];
    $sections = collect($levels)->keys()->groupBy(fn ($l) => \App\Support\FormLabel::section($l));
    $sectionStyle = [
        'preschool' => ['Pre-school', 'bg-[#f5922f]/10 text-[#c46a10]'],
        'primary' => ['Primary', 'bg-[#42a5dc]/10 text-[#2c8fc6]'],
        'jhs' => ['JHS', 'bg-[#967adc]/10 text-[#7d62c4]'],
    ];
@endphp

<div class="space-y-6">
    <x-dashboard-hero title="Term Fee Schedule" :eyebrow="$term ? $term->full_name : 'No term selected'"
        subtitle="Set what each class pays for each fee item this term, then bill the pupils. Optional items only some pupils take (e.g. bus fee) are added per pupil.">
        <x-slot:chips>
            <span><i class="fas fa-user-graduate"></i> {{ number_format($activePupils) }} pupils in school</span>
            <span><i class="fas fa-file-invoice"></i> {{ number_format($billedCount) }} billed this term</span>
            @if($term && $billedCount < $activePupils && $stats['classes'])
                <span class="!bg-yellow-400/20 !ring-yellow-300/40"><i class="fas fa-triangle-exclamation"></i> {{ $activePupils - $billedCount }} not billed yet</span>
            @endif
        </x-slot:chips>
        @if($term)
            <a class="primary" href="{{ route('fees.billing', ['semester_id' => $term->id]) }}"><i class="fas fa-file-invoice text-[#42a5dc]"></i> Bill pupils</a>
        @endif
        <a href="{{ route('fees.charges.index', ['semester_id' => $term?->id]) }}"><i class="fas fa-tags"></i> Billed items</a>
    </x-dashboard-hero>

    @if($terms->isEmpty())
        <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800 ring-1 ring-yellow-200">
            No terms have been created yet. Create the academic year and its terms under <a href="{{ route('semesters.index') }}" class="font-semibold underline">Terms</a> first.
        </div>
    @else
        <!-- Tiles -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile color="blue" icon="fa-list-check" title="Fee Items Set" :sub="'of ' . count($categories) . ' available items'" :value="$stats['items']" />
            <x-stat-tile color="green" icon="fa-chalkboard" title="Classes With Fees" :sub="'of ' . count($levels) . ' class levels'" :value="$stats['classes']" />
            <x-stat-tile color="orange" icon="fa-arrows-left-right" title="Fee Per Pupil" sub="Lowest – highest class"
                :value="$stats['max'] ? number_format($stats['min'], 0) . ' – ' . number_format($stats['max'], 0) : '—'" />
            <x-stat-tile color="purple" icon="fa-sack-dollar" title="Expected Income" sub="Class totals × pupils in each" :value="$money($stats['expected'])" />
        </div>

        <!-- Term + copy -->
        <div class="flex flex-col gap-4 rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100 md:flex-row md:items-end md:justify-between">
            <form method="GET" action="{{ route('fee-structures.index') }}">
                <label class="mb-1 block text-xs font-medium text-gray-600"><i class="fas fa-calendar-days mr-1 text-[#42a5dc]"></i> Term</label>
                <select name="semester_id" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}{{ $t->is_current ? ' — current' : '' }}</option>
                    @endforeach
                </select>
            </form>

            <form method="POST" action="{{ route('fee-structures.copy') }}" class="flex flex-wrap items-end gap-2"
                  onsubmit="return confirm('Copy the fees from the chosen term into {{ $term?->full_name }}? Amounts already set here will be replaced.')">
                @csrf
                <input type="hidden" name="semester_id" value="{{ $term?->id }}">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600"><i class="fas fa-copy mr-1 text-[#967adc]"></i> Start from another term's fees</label>
                    <select name="from_semester_id" class="rounded-md border-gray-300 text-sm" required>
                        <option value="">Choose a term…</option>
                        @foreach($terms as $t)
                            @continue($term && $t->id === $term->id)
                            <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md border border-[#967adc] px-4 py-2 text-sm font-semibold text-[#7d62c4] hover:bg-[#967adc]/10">Copy</button>
            </form>
        </div>

        <!-- Grid -->
        <form method="POST" action="{{ route('fee-structures.save') }}" id="fee-grid" class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
            @csrf
            <input type="hidden" name="semester_id" value="{{ $term->id }}">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-md bg-[#42a5dc] text-white"><i class="fas fa-table-cells"></i></span>
                    <div>
                        <h3 class="font-semibold text-gray-900">Fees for {{ $term->full_name }} (GH₵)</h3>
                        <p class="text-xs text-gray-500">Leave a cell blank if the item doesn't apply to that class. Use <i class="fas fa-wand-magic-sparkles"></i> to fill a whole row.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span id="unsaved" class="hidden rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-800"><i class="fas fa-circle-exclamation mr-1"></i> Unsaved changes</span>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-[#8cc152] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#74a83b]"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-[11px] font-bold uppercase tracking-wide">
                            <th class="sticky left-0 z-10 bg-white"></th>
                            @foreach($sections as $section => $sectionLevels)
                                <th colspan="{{ $sectionLevels->count() }}" class="px-2 pt-3 text-center">
                                    <span class="block rounded-t-md py-1 {{ $sectionStyle[$section][1] ?? '' }}">{{ $sectionStyle[$section][0] ?? $section }}</span>
                                </th>
                            @endforeach
                        </tr>
                        <tr class="bg-gray-50 text-xs uppercase text-gray-500">
                            <th class="sticky left-0 z-10 min-w-[14rem] bg-gray-50 px-4 py-3 text-left font-medium">Fee item</th>
                            @foreach($levels as $level => $label)
                                <th class="whitespace-nowrap px-2 py-3 text-center font-medium">
                                    {{ $label }}
                                    <span class="block text-[10px] font-normal normal-case text-gray-400">{{ $pupils[$level] ?? 0 }} pupils</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($categories as $slug => $name)
                            @php $rowTotal = array_sum($amounts[$slug] ?? []); @endphp
                            <tr class="group hover:bg-[#42a5dc]/5">
                                <td class="sticky left-0 z-10 bg-white px-4 py-2 group-hover:bg-[#f3f9fd]">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2 whitespace-nowrap font-medium text-gray-800">
                                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $colorFor($slug) }}"></span>{{ $name }}
                                        </span>
                                        <button type="button" class="fill-row text-gray-300 hover:text-[#42a5dc]" data-row="{{ $slug }}" data-name="{{ $name }}" title="Fill this row"><i class="fas fa-wand-magic-sparkles"></i></button>
                                    </div>
                                </td>
                                @foreach($levels as $level => $label)
                                    <td class="px-1 py-1.5">
                                        <input type="number" step="0.01" min="0" inputmode="decimal" name="amounts[{{ $slug }}][{{ $level }}]"
                                               value="{{ isset($amounts[$slug][$level]) ? number_format($amounts[$slug][$level], 2, '.', '') : '' }}"
                                               class="fee-cell w-24 rounded-md border-gray-200 bg-gray-50/60 py-1.5 text-right text-sm transition focus:border-[#42a5dc] focus:bg-white focus:ring-[#42a5dc] [&:not(:placeholder-shown)]:bg-white"
                                               placeholder="—" data-level="{{ $level }}" data-row="{{ $slug }}" data-section="{{ \App\Support\FormLabel::section($level) }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200">
                        <tr class="bg-gray-50 font-semibold text-gray-900">
                            <td class="sticky left-0 z-10 bg-gray-50 px-4 py-3">Total per pupil</td>
                            @foreach($levels as $level => $label)
                                <td class="whitespace-nowrap px-2 py-3 text-right" data-total-level="{{ $level }}">{{ number_format($totals[$level], 2) }}</td>
                            @endforeach
                        </tr>
                        <tr class="bg-[#967adc]/5 text-xs text-[#7d62c4]">
                            <td class="sticky left-0 z-10 bg-[#f6f3fc] px-4 py-2 font-semibold">Expected from class</td>
                            @foreach($levels as $level => $label)
                                <td class="whitespace-nowrap px-2 py-2 text-right" data-expected-level="{{ $level }}" data-pupils="{{ $pupils[$level] ?? 0 }}">{{ number_format($totals[$level] * ($pupils[$level] ?? 0), 0) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4">
                <p class="text-sm text-gray-600">Expected income this term: <strong class="text-[#7d62c4]" id="expected-total">{{ $money($stats['expected']) }}</strong></p>
                <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-[#8cc152] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#74a83b]"><i class="fas fa-save"></i> Save fee schedule</button>
            </div>
        </form>

        <!-- Fee items -->
        <div class="rounded-md bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <div class="mb-3 flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-[#5b6f8f] text-white"><i class="fas fa-tags"></i></span>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Fee items</h3>
                    <p class="text-xs text-gray-500">The rows above. Add your own, e.g. Lunch, Excursion, Printing.</p>
                </div>
            </div>
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach(\App\Models\FeeCategory::orderBy('name')->get() as $category)
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                        <span class="h-2 w-2 rounded-full" style="background: {{ $colorFor($category->slug) }}"></span>
                        {{ $category->name }}
                        @unless($category->is_protected)
                            <form method="POST" action="{{ route('fees.categories.destroy', $category) }}" onsubmit="return confirm('Remove {{ addslashes($category->name) }}? Only possible if nothing uses it.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-gray-400 hover:text-red-600" title="Remove"><i class="fas fa-xmark"></i></button>
                            </form>
                        @endunless
                    </span>
                @endforeach
            </div>
            <form method="POST" action="{{ route('fees.categories.store') }}" class="flex max-w-md items-center gap-2">
                @csrf
                <input type="text" name="name" placeholder="New fee item" class="w-full rounded-md border-gray-300 text-sm" required>
                <button type="submit" class="whitespace-nowrap rounded-md bg-[#5b6f8f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#4a5c7a]"><i class="fas fa-plus mr-1"></i> Add</button>
            </form>
        </div>
    @endif
</div>

<!-- Fill-row dialog -->
<div id="fill-dialog" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
        <h3 class="font-semibold text-gray-900">Fill “<span id="fill-name"></span>”</h3>
        <label class="mt-4 block text-sm font-medium text-gray-700">Amount (GH₵) — blank clears</label>
        <input id="fill-amount" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300">
        <label class="mt-3 block text-sm font-medium text-gray-700">For</label>
        <select id="fill-scope" class="mt-1 w-full rounded-md border-gray-300 text-sm">
            <option value="">All classes</option>
            @foreach($sections ?? [] as $section => $l)
                <option value="{{ $section }}">{{ $sectionStyle[$section][0] ?? $section }} only</option>
            @endforeach
            <option value="empty">Only the empty cells</option>
        </select>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" id="fill-cancel" class="rounded-md bg-gray-100 px-4 py-2 text-sm hover:bg-gray-200">Cancel</button>
            <button type="button" id="fill-apply" class="rounded-md bg-[#42a5dc] px-4 py-2 text-sm font-semibold text-white hover:bg-[#2c8fc6]">Fill</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var grid = document.getElementById('fee-grid');
        if (!grid) return;
        var dirty = false;
        var fmt = function (v, d) { return v.toLocaleString(undefined, { minimumFractionDigits: d, maximumFractionDigits: d }); };

        function recalc() {
            var expectedAll = 0;
            document.querySelectorAll('[data-total-level]').forEach(function (cell) {
                var level = cell.dataset.totalLevel, total = 0;
                grid.querySelectorAll('.fee-cell[data-level="' + level + '"]').forEach(function (i) { total += parseFloat(i.value) || 0; });
                cell.textContent = fmt(total, 2);
                var exp = document.querySelector('[data-expected-level="' + level + '"]');
                var expected = total * (parseInt(exp.dataset.pupils, 10) || 0);
                exp.textContent = fmt(expected, 0);
                expectedAll += expected;
            });
            document.getElementById('expected-total').textContent = 'GH₵ ' + fmt(expectedAll, 2);
        }
        function markDirty() { dirty = true; document.getElementById('unsaved').classList.remove('hidden'); recalc(); }

        grid.querySelectorAll('.fee-cell').forEach(function (input) { input.addEventListener('input', markDirty); });
        grid.addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

        // Fill a whole row (or one section / only empty cells) with one amount.
        var dialog = document.getElementById('fill-dialog'), currentRow = null;
        document.querySelectorAll('.fill-row').forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentRow = btn.dataset.row;
                document.getElementById('fill-name').textContent = btn.dataset.name;
                document.getElementById('fill-amount').value = '';
                dialog.classList.remove('hidden'); dialog.classList.add('flex');
                document.getElementById('fill-amount').focus();
            });
        });
        function close() { dialog.classList.add('hidden'); dialog.classList.remove('flex'); }
        document.getElementById('fill-cancel').addEventListener('click', close);
        dialog.addEventListener('click', function (e) { if (e.target === dialog) close(); });
        document.getElementById('fill-apply').addEventListener('click', function () {
            var amount = document.getElementById('fill-amount').value, scope = document.getElementById('fill-scope').value;
            grid.querySelectorAll('.fee-cell[data-row="' + currentRow + '"]').forEach(function (input) {
                if (scope === 'empty' && input.value !== '') return;
                if (scope && scope !== 'empty' && input.dataset.section !== scope) return;
                input.value = amount;
            });
            close(); markDirty();
        });
        document.getElementById('fill-amount').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('fill-apply').click(); } });
    })();
</script>
@endsection
