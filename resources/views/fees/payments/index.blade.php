@extends('components.app-layout')

@section('title', 'Pupil Fees')
@section('subtitle', $term ? $term->full_name : 'No term selected')

@section('content')
@php
    $money = fn ($v) => 'GH₵ ' . number_format((float) $v, 2);
    $query = request()->except('page');
    $tabUrl = fn ($status) => route('fees.index', array_filter(array_merge($query, ['status' => $status])));
    $currentStatus = request('status', '');
    $selectedClass = $classGroups->firstWhere('id', (int) request('class_group_id'));
@endphp

<div class="space-y-6">
    <!-- Hero -->
    <x-dashboard-hero title="Pupil Fees" :eyebrow="$term ? $term->full_name : 'No term selected'"
        :subtitle="$selectedClass ? 'Showing ' . $selectedClass->name . ' — find a pupil to receive a payment.' : 'Find a pupil to receive a payment, or follow up on balances.'">
        <x-slot:chips>
            <span><i class="fas fa-user-graduate"></i> {{ number_format($statusCounts['all']) }} pupils</span>
            <span><i class="fas fa-chart-pie"></i> {{ $summary['rate'] }}% of term bills collected</span>
            <span><i class="fas fa-coins"></i> {{ $money($summary['today']) }} collected today</span>
        </x-slot:chips>
        <a class="primary" href="{{ route('fees.report', ['semester_id' => $term?->id]) }}"><i class="fas fa-chart-pie text-[#42a5dc]"></i> Collection Report</a>
        <a href="{{ route('fees.export.excel', request()->query()) }}"><i class="fas fa-file-excel"></i> Excel</a>
        <a href="{{ route('fees.export.pdf', request()->query()) }}"><i class="fas fa-file-pdf"></i> PDF</a>
    </x-dashboard-hero>

    <!-- Tiles -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile color="blue" icon="fa-file-invoice" title="Billed This Term" :sub="$summary['pupils'] . ' pupil(s) shown'" :value="$money($summary['billed'])"
            :href="$term ? route('fees.billing', ['semester_id' => $term->id]) : null" />
        <x-stat-tile color="green" icon="fa-sack-dollar" title="Collected This Term" sub="Payments against this term" :value="$money($summary['paid'])"
            :badge="$summary['rate'] . '% collected'" :href="route('fees.report', ['semester_id' => $term?->id])" />
        <x-stat-tile color="red" icon="fa-circle-exclamation" title="Still Owed" sub="All terms, pupils shown" :value="$money($summary['outstanding'])"
            :href="$tabUrl('debtors')" />
        <x-stat-tile color="orange" icon="fa-users" title="Pupils Owing" :sub="$statusCounts['settled'] . ' fully paid • ' . $statusCounts['creditor'] . ' in credit'"
            :value="number_format($statusCounts['debtor'])" :href="$tabUrl('debtors')" />
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('fees.index') }}" class="rounded-md bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <input type="hidden" name="status" value="{{ $currentStatus }}">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
            <div class="md:col-span-5">
                <label class="mb-1 block text-xs font-medium text-gray-600">Find a pupil</label>
                <div class="relative">
                    <i class="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or admission no. — one match opens their account" autofocus
                           class="w-full rounded-md border-gray-300 pl-9 text-sm focus:border-[#42a5dc] focus:ring-[#42a5dc]">
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600">Term</label>
                <select name="semester_id" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600">Class</label>
                <select name="class_group_id" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                    <option value="">All classes</option>
                    @foreach($classGroups as $classGroup)
                        <option value="{{ $classGroup->id }}" @selected(request('class_group_id') == $classGroup->id)>{{ $classGroup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600">Sort by</label>
                <select name="sort" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                    <option value="">Class, then name</option>
                    <option value="balance" @selected(request('sort') === 'balance')>Highest balance first</option>
                    <option value="paid" @selected(request('sort') === 'paid')>Most paid this term</option>
                </select>
            </div>
            <div class="flex gap-2 md:col-span-1">
                <button type="submit" class="flex-1 rounded-md bg-[#42a5dc] px-3 py-2 text-sm font-semibold text-white hover:bg-[#2c8fc6]" title="Search"><i class="fas fa-filter"></i></button>
                <a href="{{ route('fees.index') }}" class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-600 hover:bg-gray-200" title="Reset"><i class="fas fa-rotate-left"></i></a>
            </div>
        </div>
    </form>

    <!-- List -->
    <div class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
        <form method="POST" action="{{ route('fees.reminders') }}">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <!-- Status tabs -->
                <div class="flex flex-wrap gap-1.5 text-sm">
                    @foreach(['' => ['All', 'all', 'bg-[#5b6f8f]'], 'debtors' => ['Owing', 'debtor', 'bg-[#da4453]'], 'settled' => ['Fully paid', 'settled', 'bg-[#8cc152]'], 'creditors' => ['In credit', 'creditor', 'bg-[#967adc]']] as $value => [$label, $key, $color])
                        <a href="{{ $tabUrl($value) }}"
                           class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 font-medium transition {{ $currentStatus === $value ? $color . ' text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ $label }}
                            <span class="rounded-full px-1.5 text-xs {{ $currentStatus === $value ? 'bg-white/25' : 'bg-white text-gray-500' }}">{{ $statusCounts[$key] }}</span>
                        </a>
                    @endforeach
                </div>
                @can('send-sms')
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md border border-[#42a5dc] px-3 py-1.5 text-sm font-semibold text-[#2c8fc6] hover:bg-[#42a5dc]/10"
                            onclick="const n=document.querySelectorAll('.pupil-box:checked').length; if(!n){alert('Tick the pupils whose parents should get a reminder.'); return false;} return confirm('Send an SMS balance reminder to ' + n + ' parent(s)?')">
                        <i class="fas fa-sms"></i> SMS reminder to ticked
                    </button>
                @endcan
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" onclick="document.querySelectorAll('.pupil-box').forEach(b => b.checked = this.checked)" class="rounded border-gray-300" title="Tick all pupils owing"></th>
                            <th class="px-4 py-3 text-left font-medium">Pupil</th>
                            <th class="px-4 py-3 text-right font-medium">Bal. b/f</th>
                            <th class="px-4 py-3 text-right font-medium">Term bill</th>
                            <th class="w-48 px-4 py-3 text-left font-medium">Paid this term</th>
                            <th class="px-4 py-3 text-right font-medium">Balance</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($students as $row)
                            @php
                                $student = $row['student'];
                                $due = max(0, $row['term_bill'] + max(0, $row['brought_forward']));
                                $pct = $due > 0 ? min(100, round($row['term_paid'] / $due * 100)) : ($row['balance'] <= 0.009 ? 100 : 0);
                                $badge = ['debtor' => ['Owing', 'bg-red-50 text-red-700 ring-red-100'], 'creditor' => ['In credit', 'bg-purple-50 text-purple-700 ring-purple-100'], 'settled' => ['Paid', 'bg-green-50 text-green-700 ring-green-100']][$row['status']];
                            @endphp
                            <tr class="group hover:bg-[#42a5dc]/5">
                                <td class="px-4 py-3 text-center">
                                    @if($row['status'] === 'debtor')
                                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="pupil-box rounded border-gray-300">
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('fees.show', ['student' => $student, 'semester_id' => $term?->id]) }}" class="flex items-center gap-3">
                                        <x-student-photo :student="$student" class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-gray-900 group-hover:text-[#2c8fc6]">{{ $student->full_name }}</span>
                                            <span class="block truncate text-xs text-gray-500">{{ $student->index_number }} • {{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right {{ $row['brought_forward'] > 0.009 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($row['brought_forward'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-gray-700">
                                    @if($row['term_bill'] > 0)
                                        {{ number_format($row['term_bill'], 2) }}
                                    @else
                                        <span class="rounded bg-yellow-50 px-1.5 py-0.5 text-xs text-yellow-700">Not billed</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-2 rounded-full {{ $pct >= 100 ? 'bg-[#8cc152]' : ($pct >= 50 ? 'bg-[#42a5dc]' : 'bg-[#f5922f]') }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="w-20 whitespace-nowrap text-right text-xs font-medium text-gray-700">{{ number_format($row['term_paid'], 2) }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <span class="block font-semibold {{ $row['status'] === 'debtor' ? 'text-red-600' : ($row['status'] === 'creditor' ? 'text-purple-600' : 'text-green-600') }}">{{ number_format(abs($row['balance']), 2) }}</span>
                                    <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $badge[1] }}">{{ $badge[0] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <a href="{{ route('fees.show', ['student' => $student, 'semester_id' => $term?->id]) }}"
                                       class="inline-flex items-center gap-1.5 rounded-md bg-[#8cc152] px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-[#74a83b]">
                                        <i class="fas fa-cash-register"></i> Receive
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <i class="fas fa-magnifying-glass mb-3 block text-3xl text-gray-300"></i>
                                    <p class="font-medium text-gray-600">No pupils match these filters.</p>
                                    <a href="{{ route('fees.index') }}" class="mt-1 inline-block text-sm text-[#2c8fc6] hover:underline">Clear filters</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        @if($students->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $students->links() }}</div>
        @endif
    </div>
</div>
@endsection
