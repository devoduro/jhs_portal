@extends('components.app-layout')

@section('title', 'Dashboard')
@section('subtitle', $term ? $term->full_name : 'Your classes and subjects')

@section('content')
<div class="space-y-6">
    <div class="gradient-bg rounded-lg shadow-lg p-6 text-white">
        <h2 class="text-2xl font-bold mb-1">Welcome, {{ auth()->user()->name }}</h2>
        <p class="opacity-90">Enter your pupils' scores and remarks here. They go straight onto the terminal reports.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500">Classes I'm in charge of</p>
            <p class="text-3xl font-bold">{{ $classCards->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500">Subjects I teach</p>
            <p class="text-3xl font-bold">{{ $subjects->count() }}</p>
        </div>
        <a href="{{ route('assignments.index') }}" class="bg-white rounded-lg shadow p-5 hover:shadow-md">
            <p class="text-sm text-gray-500">Assignments waiting to be marked</p>
            <p class="text-3xl font-bold">{{ $pendingGradingCount }}</p>
            <p class="text-xs text-gray-500">{{ $activeAssignmentsCount }} active assignments · {{ $activeQuizzesCount }} quizzes</p>
        </a>
    </div>

    @if($classCards->isNotEmpty())
        <div class="bg-white rounded-lg shadow">
            <h3 class="px-6 py-4 border-b font-semibold text-gray-900">My classes</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
                @foreach($classCards as $card)
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $card['class']->name }}</p>
                                <p class="text-xs text-gray-500">{{ $card['roll'] }} pupils</p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full {{ $card['scores'] >= 100 ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">Scores {{ $card['scores'] }}%</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-sm">
                            <a href="{{ route('scores.index', ['class_group_id' => $card['class']->id]) }}" class="px-3 py-1 rounded bg-primary-600 text-white hover:bg-primary-700"><i class="fas fa-pen-alt mr-1"></i>Scores</a>
                            <a href="{{ route('term-reports.index', ['class_group_id' => $card['class']->id]) }}" class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"><i class="fas fa-comment-dots mr-1"></i>Remarks</a>
                            <a href="{{ route('scores.broadsheet', ['classGroup' => $card['class']]) }}" class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"><i class="fas fa-table mr-1"></i>Broadsheet</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow">
        <h3 class="px-6 py-4 border-b font-semibold text-gray-900">Subjects I teach</h3>
        @if($subjects->isEmpty())
            <p class="px-6 py-6 text-gray-500 text-sm">You haven't been assigned any subjects yet.</p>
        @else
            <table class="min-w-full text-sm">
                @foreach($subjects as $subject)
                    <tr class="border-b border-gray-100">
                        <td class="px-6 py-3 font-medium">{{ $subject->title }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ \App\Support\FormLabel::label($subject->level) }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('scores.index') }}" class="text-primary-600 hover:underline">Enter scores</a>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>
@endsection
