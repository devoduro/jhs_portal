<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Question') }} &mdash; {{ $quiz->title }}</h2>
            <x-button href="{{ route('quizzes.show', $quiz) }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Back to Quiz') }}</x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @include('quizzes.questions._form', ['quiz' => $quiz, 'question' => $question, 'action' => route('quizzes.questions.update', [$quiz, $question])])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
