@extends('components.student-app-layout')

@section('header')
    <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-2xl p-8 mb-6 shadow-lg">
        <div class="relative z-10">
            <h2 class="text-3xl font-bold mb-2">Welcome back, {{ $student->full_name }}!</h2>
            <p class="text-blue-100 flex items-center">
                <i class="fas fa-graduation-cap mr-2"></i>
                {{ $student->programme->name }} • {{ \App\Support\FormLabel::label($student->level) }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-sm bg-white/10 text-blue-50">
                    <i class="fas fa-id-badge"></i> {{ $student->index_number }}
                </span>
                @if($student->reference_number)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-sm bg-white/10 text-blue-50">
                        <i class="fas fa-hashtag"></i> Ref: {{ $student->reference_number }}
                    </span>
                @endif
                @if($student->house)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-sm bg-white/10 text-blue-50">
                        <i class="fas fa-home"></i> {{ $student->house->name }}
                    </span>
                @endif
                @if($student->boarding_status)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-sm bg-white/10 text-blue-50">
                        <i class="fas fa-{{ $student->boarding_status === 'boarder' ? 'bed' : 'walking' }}"></i> {{ $student->boarding_status === 'boarder' ? 'Boarder' : 'Day Student' }}
                    </span>
                @endif
            </div>
        </div>
        <div class="absolute right-0 top-0 h-full w-1/2 bg-gradient-to-l from-blue-500 to-transparent opacity-50"></div>
    </div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto space-y-8">
    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Aggregate Card -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl p-6 text-white shadow-lg transform transition-all duration-200 hover:scale-[1.02]">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-white/10 rounded-xl">
                    <i class="fas fa-chart-line text-2xl"></i>
                </div>
                @if($aggregateChange !== 0)
                    <span class="px-3 py-1 rounded-full text-sm {{ $aggregateChange < 0 ? 'bg-green-400/20 text-green-100' : 'bg-red-400/20 text-red-100' }}">
                        <i class="fas fa-{{ $aggregateChange < 0 ? 'arrow-up' : 'arrow-down' }} mr-1"></i>
                        {{ $aggregateChange > 0 ? '+' : '' }}{{ $aggregateChange }}
                    </span>
                @endif
            </div>
            <h3 class="text-lg font-medium text-purple-100 mb-1">Current Aggregate</h3>
            <div class="flex items-baseline">
                <span class="text-4xl font-bold">{{ $aggregate ?? 'Pending' }}</span>
                @if($gradeStanding)
                    <span class="text-purple-200 ml-2">{{ $gradeStanding }}</span>
                @endif
            </div>
        </div>

        <!-- Subjects Card -->
        <div class="bg-gradient-to-br from-amber-500 to-amber-700 rounded-2xl p-6 text-white shadow-lg transform transition-all duration-200 hover:scale-[1.02]">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-white/10 rounded-xl">
                    <i class="fas fa-book text-2xl"></i>
                </div>
            </div>
            <h3 class="text-lg font-medium text-amber-100 mb-1">Subjects</h3>
            <div class="flex items-baseline">
                <span class="text-4xl font-bold">{{ count($currentSubjectIds) }}</span>
                <span class="text-amber-200 ml-2">{{ \App\Support\FormLabel::label($student->level) }}</span>
            </div>
        </div>

        <!-- Pending Work Card -->
        <a href="{{ ($pendingAssignmentsCount ?? 0) > 0 ? route('student.assignments.index') : route('student.quizzes.index') }}"
           class="bg-gradient-to-br from-rose-500 to-rose-700 rounded-2xl p-6 text-white shadow-lg transform transition-all duration-200 hover:scale-[1.02] block">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-white/10 rounded-xl">
                    <i class="fas fa-list-check text-2xl"></i>
                </div>
            </div>
            <h3 class="text-lg font-medium text-rose-100 mb-1">Pending Work</h3>
            <div class="flex items-baseline">
                <span class="text-4xl font-bold">{{ $pendingQuizzesCount + $pendingAssignmentsCount }}</span>
            </div>
            <p class="text-rose-100 text-sm mt-2">
                {{ $pendingQuizzesCount }} quiz{{ $pendingQuizzesCount === 1 ? '' : 'zes' }} &bull; {{ $pendingAssignmentsCount }} assignment{{ $pendingAssignmentsCount === 1 ? '' : 's' }}
            </p>
        </a>
    </div>

    <!-- Today's Classes -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Today's Classes</h3>
                <a href="{{ route('student.timetable') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
                    View Full Timetable
                </a>
            </div>
        </div>
        @if(!$student->class_group_id)
            <div class="p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 text-blue-600 mb-4">
                    <i class="fas fa-users-slash text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Not Yet Assigned to a Class</h3>
                <p class="text-gray-500">Once you're assigned to a class, your timetable will appear here.</p>
            </div>
        @elseif($todayEntries->isEmpty())
            <div class="p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 text-blue-600 mb-4">
                    <i class="fas fa-mug-hot text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Classes Today</h3>
                <p class="text-gray-500">You have no lessons scheduled for today ({{ now()->format('l') }}).</p>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($todayEntries as $entry)
                    <div class="p-6 hover:bg-gray-50 transition-colors duration-200">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 bg-blue-100 rounded-lg">
                                        <i class="fas fa-calendar-day text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800">{{ $entry->subject->code ?? 'N/A' }}</h4>
                                        <p class="text-sm text-gray-500">{{ $entry->subject->title ?? '' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-6 text-right">
                                <div class="text-sm font-semibold text-gray-800">{{ substr($entry->start_time, 0, 5) }} - {{ substr($entry->end_time, 0, 5) }}</div>
                                <div class="text-sm text-gray-500 mt-1">
                                    {{ $entry->venue->name ?? 'N/A' }}
                                    @if($entry->teacher)
                                        &bull; {{ $entry->teacher->name }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Recent Results -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Recent Results</h3>
                <a href="{{ route('student.report-card') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
                    View My Semester Reports
                </a>
            </div>
        </div>
        @if($results->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach($results->take(5) as $result)
                    <div class="p-6 hover:bg-gray-50 transition-colors duration-200">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 bg-blue-100 rounded-lg">
                                        <i class="fas fa-book text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800">{{ $result->subject->code }}</h4>
                                        <p class="text-sm text-gray-500">{{ $result->subject->title }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-6 text-right">
                                <div class="inline-flex items-center px-3 py-1 rounded-lg {{ in_array($result->grade, ['A1', 'B2', 'B3']) ? 'bg-green-100 text-green-800' : (in_array($result->grade, ['C4', 'C5', 'C6']) ? 'bg-blue-100 text-blue-800' : (in_array($result->grade, ['D7', 'E8']) ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')) }}">
                                    <span class="text-lg font-bold">{{ $result->grade }}</span>
                                </div>
                                <div class="text-sm text-gray-500 mt-1">{{ number_format($result->grade_point, 1) }} GP</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 text-blue-600 mb-4">
                    <i class="fas fa-clipboard-list text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Results Yet</h3>
                <p class="text-gray-500">Your academic results will appear here once they are available.</p>
            </div>
        @endif
    </div>
</div>


    <!-- Password Change Modal -->
    @if(session('show_password_change'))
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="passwordChangeModal">
            <div class="relative top-20 mx-auto p-6 border w-[28rem] shadow-xl rounded-xl bg-white">
                <div class="flex flex-col items-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 mb-4">
                        <i class="fas fa-key text-2xl text-yellow-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Password Change Required</h3>
                    <div class="text-center mb-6">
                        <p class="text-gray-600">
                            For security reasons, please change your default password before continuing.
                        </p>
                        <p class="text-sm text-gray-500 mt-2 bg-gray-50 p-3 rounded-lg">
                            Your current password is your date of birth (YYYYMMDD)
                        </p>
                    </div>
                    <div class="w-full">
                        <a href="{{ route('password.change') }}" 
                           class="w-full inline-flex items-center justify-center px-6 py-3 bg-primary-600 text-white text-base font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Set New Password
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('passwordChangeModal');
                modal.classList.add('backdrop-blur-sm');
                modal.style.display = 'block';
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('passwordChangeModal').style.display = 'block';
            });
        </script>
    @endif
@endsection
