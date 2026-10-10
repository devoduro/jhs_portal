@extends('components.app-layout')

@section('title', 'Dashboard')
@section('subtitle', 'Your classes, subjects and pupils')

@section('content')
@php
    $pupils = $classCards->sum('roll');
    $avgScores = $classCards->count() ? round($classCards->avg('scores')) : null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="col-span-1 md:col-span-4">
        <x-dashboard-hero :title="\App\Support\Greeting::salutation(auth()->user()->name)" :eyebrow="now()->format('l, j F Y')"
            subtitle="Scores and remarks you enter here go straight onto your pupils' terminal reports.">
            <x-slot:chips>
                @if($term)<span><i class="fas fa-book-open"></i> {{ $term->full_name }}</span>@endif
                @if($term?->vacation_date)<span><i class="fas fa-umbrella-beach"></i> Vacates {{ $term->vacation_date->format('j M') }}</span>@endif
            </x-slot:chips>
            <a class="primary" href="{{ route('scores.index') }}"><i class="fas fa-pen-to-square text-[#42a5dc]"></i> Enter Scores</a>
            @if($classCards->isNotEmpty())
                <a href="{{ route('term-reports.index') }}"><i class="fas fa-comment-dots"></i> Remarks</a>
            @endif
            <a href="{{ route('teacher.timetable') }}"><i class="fas fa-calendar-days"></i> Timetable</a>
        </x-dashboard-hero>
    </div>

    <div class="col-span-1 md:col-span-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-tile color="green" icon="fa-chalkboard" title="My Classes" sub="As class teacher" :value="$classCards->count()" />
        <x-stat-tile color="blue" icon="fa-user-graduate" title="My Pupils" sub="In classes I lead" :value="$pupils" />
        <x-stat-tile color="orange" icon="fa-book" title="My Subjects" sub="Assigned to me" :value="$subjects->count()" />
        <x-stat-tile color="purple" icon="fa-pen-to-square" title="Scores Entered"
            :sub="$term ? $term->name . ' • my classes' : 'No current term'"
            :value="$avgScores !== null ? $avgScores . '%' : '—'"
            :href="route('scores.index')" />
    </div>

    @if($classCards->isEmpty() && $subjects->isEmpty())
        <div class="col-span-1 md:col-span-4 rounded-md bg-yellow-50 p-6 text-center ring-1 ring-yellow-200">
            <i class="fas fa-circle-info mb-2 text-2xl text-yellow-500"></i>
            <p class="font-medium text-yellow-900">You haven't been given a class or subject yet.</p>
            <p class="text-sm text-yellow-800">Ask the head teacher to make you class teacher of a class (Classes page) or to assign you subjects.</p>
        </div>
    @endif

    @if($classCards->isNotEmpty())
        <div class="col-span-1 md:col-span-4 rounded-md bg-white shadow-sm ring-1 ring-gray-100">
            <div class="border-b border-gray-100 p-5"><h3 class="text-lg font-bold text-gray-800">My classes</h3></div>
            <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach($classCards as $card)
                    <div class="rounded-md p-4 ring-1 ring-gray-200">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $card['class']->name }}</p>
                                <p class="text-xs text-gray-500">{{ $card['roll'] }} pupils</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $card['scores'] >= 100 ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">Scores {{ $card['scores'] }}%</span>
                        </div>
                        <div class="mt-3 h-2 rounded bg-gray-100"><div class="h-2 rounded {{ $card['scores'] >= 100 ? 'bg-[#8cc152]' : 'bg-[#42a5dc]' }}" style="width: {{ $card['scores'] }}%"></div></div>
                        <div class="mt-4 flex flex-wrap gap-2 text-sm">
                            <a href="{{ route('scores.index', ['class_group_id' => $card['class']->id]) }}" class="rounded-md bg-[#42a5dc] px-3 py-1.5 font-medium text-white hover:bg-[#2c8fc6]"><i class="fas fa-pen-to-square mr-1"></i>Scores</a>
                            <a href="{{ route('term-reports.index', ['class_group_id' => $card['class']->id]) }}" class="rounded-md bg-gray-100 px-3 py-1.5 hover:bg-gray-200"><i class="fas fa-comment-dots mr-1"></i>Remarks</a>
                            <a href="{{ route('scores.broadsheet', ['classGroup' => $card['class']]) }}" class="rounded-md bg-gray-100 px-3 py-1.5 hover:bg-gray-200"><i class="fas fa-table mr-1"></i>Broadsheet</a>
                            <a href="{{ route('report-cards.bulk-print', ['class_group_id' => $card['class']->id]) }}" target="_blank" class="rounded-md bg-gray-100 px-3 py-1.5 hover:bg-gray-200"><i class="fas fa-print mr-1"></i>Reports</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($subjects->isNotEmpty())
        <div class="col-span-1 md:col-span-3 overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-100">
            <div class="border-b border-gray-100 p-5"><h3 class="text-lg font-bold text-gray-800">Subjects I teach</h3></div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr><th class="px-5 py-2 text-left font-medium">Subject</th><th class="px-5 py-2 text-left font-medium">Class level</th><th class="px-5 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($subjects as $subject)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-800">{{ $subject->title }} <span class="text-xs text-gray-400">{{ $subject->code }}</span></td>
                            <td class="px-5 py-3 text-gray-600">{{ \App\Support\FormLabel::label($subject->level) }}</td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('scores.index') }}" class="text-primary-600 hover:underline">Enter scores</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <a href="{{ route('assignments.index') }}" class="rounded-md bg-white p-5 shadow-sm ring-1 ring-gray-100 hover:shadow-md {{ $subjects->isEmpty() ? 'md:col-span-4' : '' }}">
        <h3 class="text-sm font-semibold text-gray-700">Homework & quizzes</h3>
        <p class="mt-3 text-3xl font-bold text-gray-900">{{ $pendingGradingCount }}</p>
        <p class="text-xs text-gray-500">submission(s) waiting to be marked</p>
        <p class="mt-2 text-xs text-gray-500">{{ $activeAssignmentsCount }} active assignment(s) • {{ $activeQuizzesCount }} quiz(zes)</p>
    </a>
</div>
@endsection
