@extends('components.student-app-layout')

@section('content')
<div class="py-12">
    <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <a href="{{ route('student.assignments.index') }}" class="text-sm text-gray-500 hover:text-primary-600 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to My Assignments</a>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <h2 class="text-xl font-bold text-gray-900">{{ $assignment->title }}</h2>
            <p class="text-sm text-gray-500 mb-4">{{ $assignment->subject->code ?? '' }} - {{ $assignment->subject->title ?? '' }}</p>

            @if($assignment->description)
                <p class="text-gray-700 whitespace-pre-line mb-4">{{ $assignment->description }}</p>
            @endif

            @if($assignment->attachment_path)
                <a href="{{ asset('storage/'.$assignment->attachment_path) }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:underline mb-4">
                    <i class="fas fa-paperclip"></i> {{ $assignment->attachment_name ?? 'Download Attachment' }}
                </a>
            @endif

            <div class="grid grid-cols-2 gap-4 mb-2">
                <div class="bg-gray-50 p-4 rounded-lg text-center">
                    <div class="text-sm font-semibold {{ $assignment->isOverdue() ? 'text-red-600' : 'text-gray-900' }}">{{ $assignment->due_at->format('M j, Y g:i A') }}</div>
                    <div class="text-xs text-gray-500">{{ $assignment->isOverdue() ? 'Was Due' : 'Due' }}</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg text-center">
                    <div class="text-sm font-semibold text-gray-900">{{ $assignment->max_score ?? 'Not scored' }}</div>
                    <div class="text-xs text-gray-500">Max Score</div>
                </div>
            </div>
        </div>

        @if($submission && $submission->submitted_at)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Your Submission</h3>
                    @if($submission->is_late)
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Late</span>
                    @else
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">On Time</span>
                    @endif
                </div>

                <p class="text-xs text-gray-500 mb-3">Submitted {{ $submission->submitted_at->format('M j, Y g:i A') }}</p>

                @if($submission->submission_text)
                    <p class="text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-md p-4 mb-3">{{ $submission->submission_text }}</p>
                @endif
                @if($submission->file_path)
                    <a href="{{ asset('storage/'.$submission->file_path) }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:underline mb-3">
                        <i class="fas fa-file-download"></i> {{ $submission->file_name }}
                    </a>
                @endif

                @if($submission->isGraded())
                    <div class="mt-4 bg-blue-50 border border-blue-200 rounded-md p-4">
                        <p class="text-sm font-semibold text-blue-900">Score: {{ $submission->score ?? 0 }}{{ $assignment->max_score ? '/'.$assignment->max_score : '' }}</p>
                        @if($submission->feedback)
                            <p class="text-sm text-blue-800 mt-2">{{ $submission->feedback }}</p>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-500 italic">Not graded yet.</p>
                @endif

                @if($assignment->canSubmit())
                    <p class="text-xs text-gray-500 mt-4">You can resubmit below to replace this before the deadline.</p>
                @endif
            </div>
        @endif

        @if($assignment->canSubmit())
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <h3 class="font-semibold text-gray-900 mb-4">{{ $submission && $submission->submitted_at ? 'Resubmit Your Work' : 'Submit Your Work' }}</h3>

                @if ($errors->any())
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-md">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('student.assignments.submit', $assignment) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type Your Answer</label>
                        <textarea name="submission_text" rows="5" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">{{ old('submission_text', $submission->submission_text ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Or Upload a File</label>
                        <input type="file" name="file" class="block w-full text-sm">
                        <p class="text-xs text-gray-500 mt-1">Max 10MB. Uploading a new file replaces your previous one.</p>
                    </div>
                    <button type="submit" class="w-full bg-primary-600 text-white rounded-md px-4 py-3 text-sm font-medium hover:bg-primary-700">
                        {{ $submission && $submission->submitted_at ? 'Resubmit' : 'Submit' }}
                    </button>
                </form>
            </div>
        @elseif(!$submission || !$submission->submitted_at)
            <div class="bg-red-50 border border-red-200 rounded-md p-4 text-sm text-red-700 text-center">
                The deadline has passed and late submissions are not allowed for this assignment.
            </div>
        @endif
    </div>
</div>
@endsection
