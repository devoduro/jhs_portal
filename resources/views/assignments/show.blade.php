<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $assignment->title }}</h2>
            <div class="flex gap-2">
                <x-button href="{{ route('assignments.edit', $assignment) }}" variant="secondary" icon="fas fa-edit">{{ __('Edit') }}</x-button>
                <x-button href="{{ route('assignments.index') }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Back to Assignments') }}</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-md px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-md px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-sm text-gray-500">{{ $assignment->subject->code ?? '' }} - {{ $assignment->subject->title ?? '' }} &bull; {{ $assignment->classGroups->pluck('name')->implode(', ') }}</p>
                            @if($assignment->description)
                                <p class="text-sm text-gray-700 mt-3 whitespace-pre-line">{{ $assignment->description }}</p>
                            @endif
                            @if($assignment->attachment_path)
                                <a href="{{ asset('storage/'.$assignment->attachment_path) }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:underline mt-3">
                                    <i class="fas fa-paperclip"></i> {{ $assignment->attachment_name ?? 'Download Attachment' }}
                                </a>
                            @endif
                        </div>
                        @if($assignment->is_active)
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-gray-50 p-3 rounded-lg text-center">
                            <div class="text-sm font-semibold text-gray-900">{{ $assignment->due_at->format('M j, g:i A') }}</div>
                            <div class="text-xs text-gray-500">Due</div>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg text-center">
                            <div class="text-lg font-semibold text-blue-900">{{ $stats['eligible'] }}</div>
                            <div class="text-xs text-blue-700">Eligible Students</div>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-lg text-center">
                            <div class="text-lg font-semibold text-purple-900">{{ $stats['submitted'] }}</div>
                            <div class="text-xs text-purple-700">Submitted</div>
                        </div>
                        <div class="bg-yellow-50 p-3 rounded-lg text-center">
                            <div class="text-lg font-semibold text-yellow-900">{{ $stats['graded'] }}</div>
                            <div class="text-xs text-yellow-700">Graded</div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-xs text-gray-500 space-x-3">
                            <span><i class="fas fa-redo"></i> {{ $assignment->allow_late_submission ? 'Late submissions allowed' : 'No late submissions' }}</span>
                            @if($assignment->max_score)<span><i class="fas fa-star"></i> Max score: {{ $assignment->max_score }}</span>@endif
                            @if($assignment->notified_at)<span><i class="fas fa-envelope"></i> Students emailed {{ $assignment->notified_at->diffForHumans() }}</span>@endif
                        </div>
                        <form action="{{ route('assignments.notify', $assignment) }}" method="POST" onsubmit="return confirm('Email all eligible students about this assignment now?')">
                            @csrf
                            <button type="submit" class="text-sm text-primary-600 hover:underline"><i class="fas fa-envelope mr-1"></i> Notify Students Now</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Submissions</h3>

                    @if($eligibleStudents->isEmpty())
                        <p class="text-sm text-gray-500">No students in the selected classes yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Work</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Feedback</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($eligibleStudents as $student)
                                        @php($submission = $submissions->get($student->id))
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                {{ $student->full_name }}
                                                <div class="text-xs text-gray-500">{{ $student->index_number }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                @if(!$submission || !$submission->submitted_at)
                                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not Submitted</span>
                                                @elseif($submission->is_late)
                                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Late</span>
                                                @else
                                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">On Time</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500">{{ $submission?->submitted_at?->format('M j, g:i A') ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500">
                                                @if($submission?->file_path)
                                                    <a href="{{ asset('storage/'.$submission->file_path) }}" target="_blank" class="text-primary-600 hover:underline"><i class="fas fa-file-download"></i> File</a>
                                                @endif
                                                @if($submission?->submission_text)
                                                    <details class="inline">
                                                        <summary class="text-primary-600 cursor-pointer inline">Text</summary>
                                                        <p class="text-xs text-gray-600 mt-1 max-w-xs whitespace-pre-line">{{ $submission->submission_text }}</p>
                                                    </details>
                                                @endif
                                                @if(!$submission?->file_path && !$submission?->submission_text)-@endif
                                            </td>
                                            @if($submission?->submitted_at)
                                                @php($gradeFormId = 'grade-form-'.$submission->id)
                                                <td class="px-4 py-3">
                                                    <input type="number" step="0.5" min="0" name="score" form="{{ $gradeFormId }}" value="{{ $submission->score }}" class="w-20 pl-2 pr-1 py-1 text-sm border-gray-300 rounded-md">
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="text" name="feedback" form="{{ $gradeFormId }}" value="{{ $submission->feedback }}" placeholder="Feedback" class="w-40 pl-2 pr-1 py-1 text-sm border-gray-300 rounded-md">
                                                </td>
                                                <td class="px-4 py-3">
                                                    <button type="submit" form="{{ $gradeFormId }}" class="text-primary-600 hover:text-primary-900 text-sm"><i class="fas fa-save"></i></button>
                                                </td>
                                            @else
                                                <td class="px-4 py-3 text-sm text-gray-400">-</td>
                                                <td class="px-4 py-3 text-sm text-gray-400">-</td>
                                                <td class="px-4 py-3"></td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- A <form> can't legally wrap <td> elements inside a <tr> - browsers
                             relocate it out of the table on parse, silently detaching it from
                             its inputs. These live outside the table instead, and the score/
                             feedback/save inputs above reference them via the form="" attribute. --}}
                        @foreach($submissions as $submission)
                            @if($submission->submitted_at)
                                <form id="grade-form-{{ $submission->id }}" action="{{ route('assignments.submissions.grade', [$assignment, $submission]) }}" method="POST" class="hidden">
                                    @csrf
                                </form>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('assignments.destroy', $assignment) }}" method="POST" onsubmit="return confirm('Delete this assignment and all submissions? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="fas fa-trash">{{ __('Delete Assignment') }}</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
