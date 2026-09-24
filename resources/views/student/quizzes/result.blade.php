@extends('components.student-app-layout')

@section('content')
<div class="py-12">
    <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <a href="{{ route('student.quizzes.index') }}" class="text-sm text-gray-500 hover:text-primary-600 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to My Quizzes</a>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
            <h2 class="text-lg font-semibold text-gray-900">{{ $attempt->quiz->title }}</h2>
            <p class="text-sm text-gray-500 mb-6">{{ $attempt->quiz->subject->code ?? '' }} - {{ $attempt->quiz->subject->title ?? '' }}</p>

            <div class="inline-flex items-center justify-center w-28 h-28 rounded-full {{ $attempt->is_passed ? 'bg-green-100' : 'bg-red-100' }} mb-4">
                <span class="text-3xl font-bold {{ $attempt->is_passed ? 'text-green-700' : 'text-red-700' }}">{{ $attempt->percentage }}%</span>
            </div>

            <p class="text-lg font-semibold {{ $attempt->is_passed ? 'text-green-700' : 'text-red-700' }}">
                {{ $attempt->is_passed ? 'Passed' : 'Not Passed' }}
            </p>
            <p class="text-sm text-gray-500 mt-1">Score: {{ $attempt->score }} / {{ $attempt->total_marks }} &bull; Pass mark: {{ $attempt->quiz->pass_percentage }}%</p>

            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="bg-green-50 p-3 rounded-lg">
                    <div class="text-lg font-semibold text-green-800">{{ $breakdown->where('status', 'correct')->count() }}</div>
                    <div class="text-xs text-green-700">Correct</div>
                </div>
                <div class="bg-red-50 p-3 rounded-lg">
                    <div class="text-lg font-semibold text-red-800">{{ $breakdown->where('status', 'wrong')->count() }}</div>
                    <div class="text-xs text-red-700">Wrong</div>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <div class="text-lg font-semibold text-gray-800">{{ $breakdown->where('status', 'skipped')->count() }}</div>
                    <div class="text-xs text-gray-700">Skipped</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-md font-semibold text-gray-900 mb-4">Question Breakdown</h3>
            <div class="space-y-4">
                @foreach($breakdown as $index => $item)
                    @php($question = $item['question'])
                    <div class="border-b border-gray-100 pb-4 last:border-b-0 last:pb-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-gray-900">{{ $index + 1 }}. {{ $question->question_text }}</p>
                            @if($item['status'] === 'correct')
                                <span class="shrink-0 px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Correct</span>
                            @elseif($item['status'] === 'wrong')
                                <span class="shrink-0 px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">Wrong</span>
                            @else
                                <span class="shrink-0 px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Skipped</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 mt-2 space-y-1">
                            @if($item['answer'] && $item['answer']->selected_option)
                                <p>Your answer: <span class="{{ $item['status'] === 'correct' ? 'text-green-700' : 'text-red-700' }}">{{ $question->options[$item['answer']->selected_option] ?? $item['answer']->selected_option }}</span></p>
                            @endif
                            @if($item['status'] !== 'correct')
                                <p>Correct answer: <span class="text-green-700">{{ $question->correctAnswerText() }}</span></p>
                            @endif
                            @if($question->explanation)
                                <p class="text-gray-500 italic">{{ $question->explanation }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
