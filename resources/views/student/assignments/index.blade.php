@extends('components.student-app-layout')

@section('content')
<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">My Assignments</h2>
            <p class="text-gray-600">Work set by your teachers for the subjects you're taking.</p>
        </div>

        @foreach(['pending' => 'To Do', 'overdue' => 'Overdue', 'submitted' => 'Awaiting Grade', 'graded' => 'Graded'] as $key => $label)
            @if($assignments[$key]->isNotEmpty())
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">{{ $label }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($assignments[$key] as $assignment)
                            <a href="{{ route('student.assignments.show', $assignment) }}" class="block bg-white rounded-lg shadow-sm border {{ $key === 'overdue' ? 'border-red-200' : 'border-gray-200' }} p-5 hover:shadow-md transition">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-semibold text-gray-900">{{ $assignment->title }}</h4>
                                        <p class="text-sm text-gray-500">{{ $assignment->subject->code ?? '' }} - {{ $assignment->subject->title ?? '' }}</p>
                                    </div>
                                    @if($key === 'graded' && $assignment->my_submission)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ $assignment->my_submission->score ?? 0 }}{{ $assignment->max_score ? '/'.$assignment->max_score : '' }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs {{ $key === 'overdue' ? 'text-red-600' : 'text-gray-500' }} mt-3">
                                    <i class="fas fa-clock mr-1"></i>
                                    {{ $key === 'overdue' ? 'Was due' : 'Due' }} {{ $assignment->due_at->format('M j, Y g:i A') }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        @if(collect($assignments)->every(fn($group) => $group->isEmpty()))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center text-gray-500">
                No assignments yet.
            </div>
        @endif
    </div>
</div>
@endsection
