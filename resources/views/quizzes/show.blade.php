<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $quiz->title }}</h2>
            <div class="flex gap-2">
                <x-button href="{{ route('quizzes.edit', $quiz) }}" variant="secondary" icon="fas fa-edit">{{ __('Edit') }}</x-button>
                <x-button href="{{ route('quizzes.index') }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Back to Quizzes') }}</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-md px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-sm text-gray-500">{{ $quiz->subject->code ?? '' }} - {{ $quiz->subject->title ?? '' }} &bull; {{ $quiz->classGroup->name ?? 'All classes at ' . \App\Support\FormLabel::label($quiz->subject->level) }}</p>
                            @if($quiz->description)
                                <p class="text-sm text-gray-700 mt-2">{{ $quiz->description }}</p>
                            @endif
                        </div>
                        @if($quiz->is_active)
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="bg-gray-50 p-3 rounded-lg text-center">
                            <div class="text-lg font-semibold text-gray-900">{{ $quiz->duration_minutes }}m</div>
                            <div class="text-xs text-gray-500">Duration</div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg text-center">
                            <div class="text-lg font-semibold text-gray-900">{{ $quiz->pass_percentage }}%</div>
                            <div class="text-xs text-gray-500">Pass Mark</div>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg text-center">
                            <div class="text-lg font-semibold text-blue-900">{{ $stats['eligible'] }}</div>
                            <div class="text-xs text-blue-700">Eligible Students</div>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-lg text-center">
                            <div class="text-lg font-semibold text-purple-900">{{ $stats['completed'] }}</div>
                            <div class="text-xs text-purple-700">Completed</div>
                        </div>
                        <div class="bg-yellow-50 p-3 rounded-lg text-center">
                            <div class="text-lg font-semibold text-yellow-900">{{ $stats['pass_rate'] !== null ? $stats['pass_rate'].'%' : '-' }}</div>
                            <div class="text-xs text-yellow-700">Pass Rate</div>
                        </div>
                    </div>

                    <div class="mt-4 text-xs text-gray-500 space-x-3">
                        @if($quiz->has_negative_marking)
                            <span><i class="fas fa-minus-circle text-red-400"></i> Negative marking: -{{ $quiz->negative_mark_value }} per wrong answer</span>
                        @endif
                        <span><i class="fas fa-random"></i> {{ $quiz->shuffle_questions ? 'Questions shuffled per student' : 'Fixed question order' }}</span>
                        <span><i class="fas fa-redo"></i> {{ $quiz->allow_retake ? 'Retakes allowed' : 'Single attempt only' }}</span>
                        @if($quiz->start_at)<span><i class="fas fa-calendar"></i> Opens {{ $quiz->start_at->format('M j, Y g:i A') }}</span>@endif
                        @if($quiz->end_at)<span><i class="fas fa-calendar-times"></i> Closes {{ $quiz->end_at->format('M j, Y g:i A') }}</span>@endif
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('quizzes.results', $quiz) }}" class="text-primary-600 hover:underline text-sm">
                            <i class="fas fa-chart-bar mr-1"></i> View All Results ({{ $attempts->count() }})
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Questions ({{ $quiz->questions->count() }}) &bull; {{ $quiz->totalMarks() }} marks total</h3>
                        <x-button href="{{ route('quizzes.questions.create', $quiz) }}" size="sm" icon="fas fa-plus">{{ __('Add Question') }}</x-button>
                    </div>

                    @if($quiz->questions->isEmpty())
                        <div class="text-center py-8 text-gray-500">
                            No questions yet. Add at least one before students can take this quiz.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($quiz->questions as $index => $question)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900">{{ $index + 1 }}. {{ $question->question_text }}</p>
                                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-1">
                                                @foreach($question->options as $key => $text)
                                                    <div class="text-xs {{ $key === $question->correct_answer ? 'text-green-700 font-semibold' : 'text-gray-500' }}">
                                                        {{ strtoupper($key) }}. {{ $text }} @if($key === $question->correct_answer)<i class="fas fa-check-circle"></i>@endif
                                                    </div>
                                                @endforeach
                                            </div>
                                            <p class="text-xs text-gray-400 mt-2">{{ $question->marks }} mark(s) &bull; {{ $question->type === 'mcq' ? 'Multiple Choice' : 'True/False' }}</p>
                                        </div>
                                        <div class="flex gap-2 ml-4">
                                            <a href="{{ route('quizzes.questions.edit', [$quiz, $question]) }}" class="text-indigo-600 hover:text-indigo-900" title="Edit"><i class="fas fa-edit"></i></a>
                                            <form action="{{ route('quizzes.questions.destroy', [$quiz, $question]) }}" method="POST" onsubmit="return confirm('Delete this question?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('quizzes.destroy', $quiz) }}" method="POST" onsubmit="return confirm('Delete this quiz and all student attempts? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="fas fa-trash">{{ __('Delete Quiz') }}</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
