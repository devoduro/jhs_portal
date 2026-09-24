@extends('components.student-app-layout')

@section('content')
<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">My Quizzes</h2>
            <p class="text-gray-600">Timed quizzes for the subjects you're taking.</p>
        </div>

        @if($quizzes['ongoing']->isNotEmpty())
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Continue</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($quizzes['ongoing'] as $quiz)
                        <a href="{{ route('student.quizzes.take', $quiz) }}" class="block bg-white rounded-lg shadow-sm border border-yellow-200 p-5 hover:shadow-md transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-semibold text-gray-900">{{ $quiz->title }}</h4>
                                    <p class="text-sm text-gray-500">{{ $quiz->subject->code ?? '' }} - {{ $quiz->subject->title ?? '' }}</p>
                                </div>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">In Progress</span>
                            </div>
                            <p class="text-sm text-primary-600 mt-3">Resume &rarr;</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Available</h3>
            @if($quizzes['available']->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center text-gray-500">
                    No quizzes available right now.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($quizzes['available'] as $quiz)
                        <a href="{{ route('student.quizzes.show', $quiz) }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-5 hover:shadow-md transition">
                            <h4 class="font-semibold text-gray-900">{{ $quiz->title }}</h4>
                            <p class="text-sm text-gray-500">{{ $quiz->subject->code ?? '' }} - {{ $quiz->subject->title ?? '' }}</p>
                            <div class="flex gap-4 mt-3 text-xs text-gray-500">
                                <span><i class="fas fa-clock mr-1"></i>{{ $quiz->duration_minutes }} min</span>
                                <span><i class="fas fa-list-ol mr-1"></i>{{ $quiz->questions_count }} questions</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if($quizzes['upcoming']->isNotEmpty())
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Upcoming</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($quizzes['upcoming'] as $quiz)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 opacity-75">
                            <h4 class="font-semibold text-gray-900">{{ $quiz->title }}</h4>
                            <p class="text-sm text-gray-500">{{ $quiz->subject->code ?? '' }} - {{ $quiz->subject->title ?? '' }}</p>
                            <p class="text-xs text-gray-500 mt-3"><i class="fas fa-calendar mr-1"></i>Opens {{ $quiz->start_at->format('M j, Y g:i A') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($quizzes['completed']->isNotEmpty())
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Completed</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($quizzes['completed'] as $quiz)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                            <h4 class="font-semibold text-gray-900">{{ $quiz->title }}</h4>
                            <p class="text-sm text-gray-500">{{ $quiz->subject->code ?? '' }} - {{ $quiz->subject->title ?? '' }}</p>
                            @if($quiz->latest_attempt && $quiz->latest_attempt->status === 'completed')
                                <div class="flex items-center justify-between mt-3">
                                    <span class="text-sm font-medium {{ $quiz->latest_attempt->is_passed ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $quiz->latest_attempt->percentage }}% &bull; {{ $quiz->latest_attempt->is_passed ? 'Passed' : 'Failed' }}
                                    </span>
                                    <a href="{{ route('student.quizzes.result', $quiz->latest_attempt) }}" class="text-sm text-primary-600 hover:underline">View Result</a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
