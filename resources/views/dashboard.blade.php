@extends('components.app-layout')

@section('title', 'Dashboard')
@section('subtitle', $term ? $term->full_name : 'No current term set')

@section('content')
<div class="space-y-6">
    <!-- Welcome -->
    <div class="gradient-bg rounded-lg shadow-lg p-6 text-white flex flex-col md:flex-row justify-between md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold">{{ \App\Models\Setting::get('institution_name', 'Your School Name') }}</h2>
            <p class="opacity-90">
                {{ $term ? $term->full_name : 'Set the current term under Terms to get started.' }}
                @if($term?->vacation_date) &bull; Vacation {{ $term->vacation_date->format('d M Y') }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('manage-fees')
                <a href="{{ route('fees.index') }}" class="inline-flex items-center px-4 py-2 bg-white text-primary-700 rounded-lg font-medium shadow-sm hover:bg-gray-50">
                    <i class="fas fa-cash-register mr-2"></i> Receive Fees
                </a>
            @endcan
            @canany(['enter-scores', 'manage-results'])
                <a href="{{ route('scores.index') }}" class="inline-flex items-center px-4 py-2 bg-white/20 text-white rounded-lg font-medium hover:bg-white/30">
                    <i class="fas fa-pen-alt mr-2"></i> Enter Scores
                </a>
            @endcanany
            <a href="{{ route('report-cards.bulk-form') }}" class="inline-flex items-center px-4 py-2 bg-white/20 text-white rounded-lg font-medium hover:bg-white/30">
                <i class="fas fa-print mr-2"></i> Print Reports
            </a>
        </div>
    </div>

    <!-- Pupils -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500">Pupils on roll</p>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['pupils'] }}</p>
            <p class="text-xs text-gray-500">{{ $stats['boys'] }} boys · {{ $stats['girls'] }} girls</p>
        </div>
        @foreach($sections as $section)
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">{{ $section['label'] }}</p>
                <p class="text-3xl font-bold text-gray-900">{{ $section['count'] }}</p>
            </div>
        @endforeach
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500">Classes / Teachers</p>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['classes'] }} <span class="text-lg text-gray-400">/ {{ $stats['teachers'] }}</span></p>
            @if($stats['unplaced'])
                <a href="{{ route('students.index', ['unplaced' => 1]) }}" class="text-xs text-orange-600 hover:underline">{{ $stats['unplaced'] }} pupil(s) not in a class</a>
            @endif
        </div>
    </div>

    @if($showFees && $fees)
        <!-- Fees -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-green-600 rounded-lg shadow p-5 text-white">
                <p class="text-sm opacity-90">Collected today</p>
                <p class="text-2xl font-bold">GH₵ {{ number_format($fees['today'], 2) }}</p>
                <p class="text-xs opacity-80">{{ $fees['todayCount'] }} payment(s) · GH₵ {{ number_format($fees['week'], 2) }} this week</p>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Collected this term</p>
                <p class="text-2xl font-bold text-gray-900">GH₵ {{ number_format($fees['collected'], 2) }}</p>
                <p class="text-xs text-gray-500">of GH₵ {{ number_format($fees['billed'], 2) }} billed ({{ $fees['rate'] }}%)</p>
                <div class="mt-2 h-2 bg-gray-100 rounded"><div class="h-2 bg-green-500 rounded" style="width: {{ min(100, $fees['rate']) }}%"></div></div>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Total owed by parents</p>
                <p class="text-2xl font-bold text-red-600">GH₵ {{ number_format($fees['outstanding'], 2) }}</p>
                <a href="{{ route('fees.index', ['status' => 'debtors', 'sort' => 'balance']) }}" class="text-xs text-primary-600 hover:underline">{{ $fees['debtors'] }} pupil(s) owing →</a>
            </div>
            <div class="bg-white rounded-lg shadow p-5">
                <p class="text-sm text-gray-500">Not yet billed this term</p>
                <p class="text-2xl font-bold {{ $fees['unbilled'] ? 'text-orange-600' : 'text-gray-900' }}">{{ $fees['unbilled'] }} pupil(s)</p>
                @if($fees['unbilled'] && $term)
                    <a href="{{ route('fees.billing', ['semester_id' => $term->id]) }}" class="text-xs text-primary-600 hover:underline">Bill them now →</a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Daily collections (last 14 days)</h3>
                @php
                    $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d));
                    $max = max(1, $fees['daily']->max() ?? 1);
                @endphp
                <div class="flex items-end gap-1 h-40">
                    @foreach($days as $day)
                        @php $v = (float) ($fees['daily'][$day->toDateString()] ?? 0); @endphp
                        <div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ $day->format('D d M') }}: GH₵ {{ number_format($v, 2) }}">
                            <div class="w-full bg-primary-500 rounded-t" style="height: {{ $v > 0 ? max(3, $v / $max * 100) : 0 }}%"></div>
                            <span class="text-[10px] text-gray-400 mt-1">{{ $day->format('d') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">Latest payments</h3>
                    <a href="{{ route('fees.report') }}" class="text-sm text-primary-600 hover:underline">Cash book</a>
                </div>
                <table class="min-w-full text-sm">
                    @forelse($fees['recent'] as $payment)
                        <tr class="border-b border-gray-100">
                            <td class="px-6 py-2">
                                <a href="{{ route('fees.show', $payment->student_id) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $payment->student->full_name ?? '—' }}</a>
                                <div class="text-xs text-gray-500">{{ $payment->student?->classGroup->name }} · {{ $payment->receipt_number }}</div>
                            </td>
                            <td class="px-6 py-2 text-gray-500 whitespace-nowrap">{{ $payment->payment_date->format('d M') }}</td>
                            <td class="px-6 py-2 text-right font-semibold whitespace-nowrap">GH₵ {{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-6 py-6 text-center text-gray-400">No payments recorded yet.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    @endif

    @if($showAcademics && $classProgress->isNotEmpty())
        <!-- Terminal report progress -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h3 class="font-semibold text-gray-900">Terminal report progress — {{ $term->full_name }}</h3>
                <p class="text-sm text-gray-500">How much of each class's scores and remarks have been entered.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class teacher</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pupils</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase w-48">Scores</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase w-48">Remarks</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($classProgress as $row)
                            <tr>
                                <td class="px-6 py-3 font-medium">{{ $row['class']->name }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $row['class']->classTeacher->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-right">{{ $row['roll'] }}</td>
                                @foreach(['scores', 'remarks'] as $k)
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-2 bg-gray-100 rounded"><div class="h-2 rounded {{ $row[$k] >= 100 ? 'bg-green-500' : 'bg-primary-500' }}" style="width: {{ $row[$k] }}%"></div></div>
                                            <span class="text-xs text-gray-500 w-9 text-right">{{ $row[$k] }}%</span>
                                        </div>
                                    </td>
                                @endforeach
                                <td class="px-6 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('scores.broadsheet', ['classGroup' => $row['class'], 'semester_id' => $term->id]) }}" class="text-primary-600 hover:underline text-xs mr-3">Broadsheet</a>
                                    <a href="{{ route('report-cards.bulk-print', ['class_group_id' => $row['class']->id, 'semester_id' => $term->id]) }}" target="_blank" class="text-primary-600 hover:underline text-xs">Reports</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
