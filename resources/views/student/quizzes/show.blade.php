@extends('components.student-app-layout')

@section('content')
<div class="py-12">
    <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <a href="{{ route('student.quizzes.index') }}" class="text-sm text-gray-500 hover:text-primary-600 mb-4 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to My Quizzes</a>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <h2 class="text-xl font-bold text-gray-900">{{ $quiz->title }}</h2>
            <p class="text-sm text-gray-500 mb-6">{{ $quiz->subject->code ?? '' }} - {{ $quiz->subject->title ?? '' }}</p>

            @if($quiz->description)
                <p class="text-gray-700 mb-4">{{ $quiz->description }}</p>
            @endif

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-gray-50 p-4 rounded-lg text-center">
                    <div class="text-xl font-semibold text-gray-900">{{ $quiz->duration_minutes }} min</div>
                    <div class="text-xs text-gray-500">Duration</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg text-center">
                    <div class="text-xl font-semibold text-gray-900">{{ $quiz->questions_count }}</div>
                    <div class="text-xs text-gray-500">Questions</div>
                </div>
            </div>

            @if($quiz->instructions)
                <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6 text-sm text-blue-800">
                    <p class="font-medium mb-1">Instructions</p>
                    <p>{{ $quiz->instructions }}</p>
                </div>
            @endif

            <ul class="text-sm text-gray-600 space-y-1 mb-6">
                <li><i class="fas fa-check text-gray-400 w-4"></i> You need {{ $quiz->pass_percentage }}% to pass.</li>
                @if($quiz->has_negative_marking)
                    <li><i class="fas fa-exclamation-triangle text-amber-500 w-4"></i> Wrong answers deduct {{ $quiz->negative_mark_value }} mark(s) each.</li>
                @endif
                <li><i class="fas fa-redo text-gray-400 w-4"></i> {{ $quiz->allow_retake ? 'You may retake this quiz.' : 'You only get one attempt.' }}</li>
                <li><i class="fas fa-hourglass-half text-gray-400 w-4"></i> Once started, the timer cannot be paused - if it runs out, your answers so far are submitted automatically.</li>
            </ul>

            @if($lastAttempt && $lastAttempt->status === 'in_progress' && !$lastAttempt->isExpired())
                <form method="POST" action="{{ route('student.quizzes.start', $quiz) }}">
                    @csrf
                    <button type="submit" class="w-full bg-yellow-600 text-white rounded-md px-4 py-3 text-sm font-medium hover:bg-yellow-700">Resume Quiz</button>
                </form>
            @elseif($lastAttempt && $lastAttempt->status === 'completed' && !$quiz->allow_retake)
                <a href="{{ route('student.quizzes.result', $lastAttempt) }}" class="block text-center w-full bg-gray-100 text-gray-700 rounded-md px-4 py-3 text-sm font-medium hover:bg-gray-200">View Your Result</a>
            @elseif(!$quiz->isOpen())
                <button type="button" disabled class="w-full bg-gray-200 text-gray-500 rounded-md px-4 py-3 text-sm font-medium cursor-not-allowed">Not Available Right Now</button>
            @else
                <form method="POST" action="{{ route('student.quizzes.start', $quiz) }}" onsubmit="return confirm('Start the quiz now? The {{ $quiz->duration_minutes }}-minute timer begins immediately.')">
                    @csrf
                    <button type="submit" class="w-full bg-primary-600 text-white rounded-md px-4 py-3 text-sm font-medium hover:bg-primary-700">Start Quiz</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
