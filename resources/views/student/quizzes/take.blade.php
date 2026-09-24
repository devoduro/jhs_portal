@extends('components.student-app-layout')

@section('content')
<div class="py-8">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 sticky top-2 z-10 p-4 mb-6 flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-gray-900">{{ $quiz->title }}</h2>
                <p id="save-status" class="text-xs text-gray-400">&nbsp;</p>
            </div>
            <div id="timer" class="text-xl font-bold text-primary-700 tabular-nums">--:--</div>
        </div>

        <form id="quiz-form" method="POST" action="{{ route('student.quizzes.submit', $quiz) }}" onsubmit="return confirm('Submit your answers now? You cannot change them after submitting.')">
            @csrf
            <div class="space-y-4">
                @foreach($questions as $index => $question)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <p class="font-medium text-gray-900 mb-3">{{ $index + 1 }}. {{ $question->question_text }}</p>
                        <div class="space-y-2">
                            @foreach($question->options as $key => $text)
                                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-md hover:bg-gray-50 cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                                    <input type="radio"
                                           name="question_{{ $question->id }}"
                                           value="{{ $key }}"
                                           data-question-id="{{ $question->id }}"
                                           class="quiz-option text-primary-600"
                                           {{ ($existingAnswers[$question->id] ?? null) === (string) $key ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-700">{{ $text }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-primary-600 text-white rounded-md px-6 py-3 text-sm font-medium hover:bg-primary-700">
                    Submit Quiz
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        let secondsRemaining = {{ $secondsRemaining }};
        const timerEl = document.getElementById('timer');
        const statusEl = document.getElementById('save-status');
        const saveUrl = @json(route('student.quizzes.save-answer', $quiz));
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        function renderTimer() {
            const m = Math.floor(secondsRemaining / 60);
            const s = secondsRemaining % 60;
            timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            if (secondsRemaining <= 60) {
                timerEl.classList.add('text-red-600');
            }
        }

        renderTimer();

        const countdown = setInterval(function () {
            secondsRemaining--;
            if (secondsRemaining <= 0) {
                clearInterval(countdown);
                statusEl.textContent = 'Time is up - submitting your answers...';
                document.getElementById('quiz-form').removeAttribute('onsubmit');
                document.getElementById('quiz-form').submit();
                return;
            }
            renderTimer();
        }, 1000);

        document.querySelectorAll('.quiz-option').forEach(function (input) {
            input.addEventListener('change', function () {
                statusEl.textContent = 'Saving...';
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        question_id: input.dataset.questionId,
                        selected_option: input.value,
                    }),
                })
                    .then(function (res) {
                        if (res.status === 403) {
                            statusEl.textContent = 'Time expired.';
                            return;
                        }
                        statusEl.textContent = 'Saved';
                    })
                    .catch(function () {
                        statusEl.textContent = 'Could not save - check your connection.';
                    });
            });
        });

        window.addEventListener('beforeunload', function (e) {
            e.preventDefault();
            e.returnValue = '';
        });
        document.getElementById('quiz-form').addEventListener('submit', function () {
            window.onbeforeunload = null;
        });
    })();
</script>
@endsection
