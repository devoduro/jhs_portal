@extends('components.app-layout')

@section('title', 'Billed Items')
@section('subtitle', $term ? $term->full_name : 'All terms')

@section('content')
@php
    $money = fn ($v) => 'GH₵ ' . number_format((float) $v, 2);
    $query = request()->except('page');
    $with = fn (array $changes) => route('fees.charges.index', array_filter(array_merge($query, $changes), fn ($v) => $v !== null && $v !== ''));
    $palette = ['#42a5dc', '#8cc152', '#f5922f', '#967adc', '#5b6f8f', '#da4453', '#37bc9b', '#e8a838', '#4a89dc', '#d770ad', '#a0d468'];
    $colorFor = fn ($slug) => $palette[abs(crc32($slug)) % count($palette)];
    $activeCategory = request('category');
@endphp

<div class="space-y-6">
    <x-dashboard-hero title="Billed Items" :eyebrow="$term ? $term->full_name : 'All terms'"
        subtitle="Everything billed to pupils: term fees from the fee schedule, plus items for individual pupils such as bus fees, uniforms and discounts.">
        <x-slot:chips>
            <span><i class="fas fa-tags"></i> {{ number_format($summary['items']) }} item(s)</span>
            <span><i class="fas fa-user-graduate"></i> {{ number_format($summary['pupils']) }} pupil(s)</span>
            @if($summary['discounts'] < 0)<span><i class="fas fa-percent"></i> {{ $money(abs($summary['discounts'])) }} in discounts</span>@endif
        </x-slot:chips>
        <a class="primary" href="{{ route('fees.billing', ['semester_id' => $term?->id]) }}"><i class="fas fa-file-invoice text-[#42a5dc]"></i> Bill a Term</a>
        <a href="{{ route('fees.charges.upload') }}"><i class="fas fa-file-arrow-up"></i> Upload items</a>
        <a href="{{ route('fee-structures.index', ['semester_id' => $term?->id]) }}"><i class="fas fa-list-ol"></i> Fee Schedule</a>
    </x-dashboard-hero>

    <!-- Tiles -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile color="blue" icon="fa-sack-dollar" title="Total Billed" sub="For the items shown" :value="$money($summary['total'])" />
        <x-stat-tile color="green" icon="fa-tags" title="Items" :sub="$summary['pupils'] . ' pupil(s)'" :value="number_format($summary['items'])" />
        <x-stat-tile color="orange" icon="fa-user-tag" title="Individual Items" sub="Bus, uniform, extras…" :value="number_format($summary['individual'])"
            :href="$with(['type' => 'individual', 'category' => null])" />
        <x-stat-tile color="purple" icon="fa-percent" title="Discounts" sub="Negative items (waivers)" :value="$money(abs($summary['discounts']))"
            :href="$with(['type' => 'discount', 'category' => null])" />
    </div>

    <!-- Fee item chips -->
    @if($byCategory->isNotEmpty())
        <div class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">By fee item <span class="font-normal text-gray-400">— click to filter</span></h3>
                <x-modal id="manage-categories-modal" title="Fee items" maxWidth="lg">
                    <x-slot name="trigger">
                        <button type="button" class="text-xs font-medium text-[#2c8fc6] hover:underline"><i class="fas fa-gear mr-1"></i> Manage fee items</button>
                    </x-slot>
                    <div class="space-y-4">
                        <ul class="divide-y divide-gray-100 text-sm">
                            @foreach($categories as $cat)
                                <li class="flex items-center justify-between py-2">
                                    <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $colorFor($cat->slug) }}"></span>{{ $cat->name }}</span>
                                    @if($cat->is_protected)
                                        <span class="text-xs text-gray-400">Required</span>
                                    @else
                                        <form action="{{ route('fees.categories.destroy', $cat) }}" method="POST" onsubmit="return confirm('Remove “{{ $cat->name }}”? Only possible if nothing uses it.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-gray-400 hover:text-red-600" title="Remove"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <form action="{{ route('fees.categories.store') }}" method="POST" class="flex items-end gap-2">
                            @csrf
                            <input type="text" name="name" placeholder="New item, e.g. Excursion" class="w-full rounded-md border-gray-300 text-sm" required>
                            <x-button type="submit" icon="fas fa-plus">Add</x-button>
                        </form>
                    </div>
                </x-modal>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $with(['category' => null]) }}"
                   class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition {{ !$activeCategory ? 'bg-[#5b6f8f] text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    All items
                </a>
                @foreach($byCategory as $cat)
                    @php $active = $activeCategory === $cat['slug']; @endphp
                    <a href="{{ $with(['category' => $cat['slug']]) }}"
                       class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition {{ $active ? 'text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                       @if($active) style="background: {{ $colorFor($cat['slug']) }}" @endif>
                        @unless($active)<span class="h-2.5 w-2.5 rounded-full" style="background: {{ $colorFor($cat['slug']) }}"></span>@endunless
                        {{ $cat['name'] }}
                        <span class="rounded-full px-1.5 text-xs {{ $active ? 'bg-white/25' : 'bg-white text-gray-500' }}">{{ $cat['count'] }} • {{ number_format($cat['total'], 0) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Filters -->
    <form method="GET" action="{{ route('fees.charges.index') }}" class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <input type="hidden" name="category" value="{{ $activeCategory }}">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
            <div class="md:col-span-4">
                <label class="mb-1 block text-xs font-medium text-gray-600">Pupil</label>
                <div class="relative">
                    <i class="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or admission no." class="w-full rounded-md border-gray-300 pl-9 text-sm">
                </div>
            </div>
            <div class="md:col-span-3">
                <label class="mb-1 block text-xs font-medium text-gray-600">Term</label>
                <select name="semester_id" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                    <option value="">All terms</option>
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600">Class</label>
                <select name="class_group_id" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                    <option value="">All classes</option>
                    @foreach($classGroups as $c)
                        <option value="{{ $c->id }}" @selected(request('class_group_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600">Type</label>
                <select name="type" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="term" @selected(request('type') === 'term')>Term bills</option>
                    <option value="individual" @selected(request('type') === 'individual')>Individual items</option>
                    <option value="discount" @selected(request('type') === 'discount')>Discounts only</option>
                </select>
            </div>
            <div class="flex gap-2 md:col-span-1">
                <button type="submit" class="flex-1 rounded-md bg-[#42a5dc] px-3 py-2 text-sm text-white hover:bg-[#2c8fc6]" title="Search"><i class="fas fa-filter"></i></button>
                <a href="{{ route('fees.charges.index') }}" class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-600 hover:bg-gray-200" title="Reset"><i class="fas fa-rotate-left"></i></a>
            </div>
        </div>
    </form>

    <!-- List -->
    <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
        <form id="bulk-delete-form" method="POST" action="{{ route('fees.charges.bulk-destroy') }}" onsubmit="return confirm('Remove the ticked items from pupils\' bills? Their balances will go down.')">@csrf</form>

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <p class="text-sm text-gray-600">{{ number_format($charges->total()) }} item(s){{ $activeCategory ? ' • ' . ($categories->firstWhere('slug', $activeCategory)->name ?? $activeCategory) : '' }}</p>
            <div class="flex items-center gap-3">
                <select onchange="location.href = this.value" class="rounded-md border-gray-300 py-1 text-xs">
                    @foreach([25, 50, 100, 200] as $n)
                        <option value="{{ $with(['per_page' => $n]) }}" @selected((int) request('per_page', 25) === $n)>{{ $n }} per page</option>
                    @endforeach
                </select>
                <button type="submit" form="bulk-delete-form" id="bulk-delete-btn" disabled
                        class="inline-flex items-center gap-2 rounded-md border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="fas fa-trash"></i> Remove ticked (<span id="selected-count">0</span>)
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="w-10 px-4 py-3"><input type="checkbox" id="select-all" class="rounded border-gray-300"></th>
                        <th class="px-4 py-3 text-left font-medium">Pupil</th>
                        <th class="px-4 py-3 text-left font-medium">Item</th>
                        <th class="px-4 py-3 text-left font-medium">Term</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3 text-left font-medium">Added</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($charges as $charge)
                        @php $isTermBill = $charge->notes === 'Term bill'; $color = $colorFor($charge->category); @endphp
                        <tr class="hover:bg-[#42a5dc]/5">
                            <td class="px-4 py-3 text-center"><input type="checkbox" name="charge_ids[]" value="{{ $charge->id }}" form="bulk-delete-form" class="charge-checkbox rounded border-gray-300"></td>
                            <td class="px-4 py-3">
                                @if($charge->student)
                                    <a href="{{ route('fees.show', ['student' => $charge->student, 'semester_id' => $charge->semester_id]) }}" class="flex items-center gap-3">
                                        <x-student-photo :student="$charge->student" class="h-8 w-8 shrink-0 rounded-full object-cover" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-gray-900 hover:text-[#2c8fc6]">{{ $charge->student->full_name }}</span>
                                            <span class="block truncate text-xs text-gray-500">{{ $charge->student->index_number }} • {{ $charge->student->classGroup->name ?? \App\Support\FormLabel::label($charge->student->level) }}</span>
                                        </span>
                                    </a>
                                @else
                                    <span class="text-gray-400">Pupil removed</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" style="background: {{ $color }}1a; color: {{ $color }}">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $color }}"></span>{{ $charge->categoryLabel() }}
                                </span>
                                <span class="mt-1 block text-xs text-gray-400">{{ $isTermBill ? 'Term bill' : ($charge->notes ?: 'Individual item') }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $charge->semester?->full_name ?? ($charge->academicYear->name ?? '—') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold {{ $charge->amount < 0 ? 'text-purple-600' : 'text-gray-900' }}">
                                {{ $charge->amount < 0 ? '−' : '' }}{{ number_format(abs($charge->amount), 2) }}
                                @if($charge->amount < 0)<span class="block text-[11px] font-medium">discount</span>@endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                {{ $charge->created_at?->format('d M Y') }}
                                <span class="block text-gray-400">{{ $charge->recordedBy->name ?? 'System' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if($charge->student)
                                    <a href="{{ route('fees.show', ['student' => $charge->student, 'semester_id' => $charge->semester_id]) }}" class="mr-3 text-[#2c8fc6] hover:text-[#1f6f9c]" title="Fee account"><i class="fas fa-wallet"></i></a>
                                @endif
                                <form action="{{ route('fees.charges.destroy', $charge) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700" title="Remove" onclick="return confirm('Remove {{ addslashes($charge->categoryLabel()) }} from {{ addslashes($charge->student->full_name ?? 'this pupil') }}\'s bill?')"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <i class="fas fa-tags mb-3 block text-3xl text-gray-300"></i>
                                <p class="font-medium text-gray-600">Nothing billed{{ $term ? ' for ' . $term->full_name : '' }} matches these filters.</p>
                                <p class="mt-1 text-sm text-gray-500">Bill the whole school from <a href="{{ route('fees.billing', ['semester_id' => $term?->id]) }}" class="text-[#2c8fc6] hover:underline">Bill a Term</a>, or add an item from a pupil's fee account.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($charges->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $charges->links() }}</div>
        @endif
    </div>
</div>

<script>
    (function () {
        var boxes = document.querySelectorAll('.charge-checkbox'), all = document.getElementById('select-all');
        var btn = document.getElementById('bulk-delete-btn'), count = document.getElementById('selected-count');
        function update() {
            var n = document.querySelectorAll('.charge-checkbox:checked').length;
            count.textContent = n; btn.disabled = n === 0;
            all.checked = n > 0 && n === boxes.length;
        }
        all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); update(); });
        boxes.forEach(function (b) { b.addEventListener('change', update); });
    })();
</script>
@endsection
