@php
    $link = fn (bool $active) => 'flex items-center gap-3 px-4 py-2.5 text-gray-600 transition-all duration-200 hover:bg-primary-50 hover:text-primary-600 rounded-lg ' . ($active ? 'bg-primary-50 text-primary-600 font-medium' : '');
    $heading = 'px-4 pt-4 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400';
    $user = auth()->user();
@endphp

<a href="{{ route('dashboard') }}" class="{{ $link(request()->routeIs('dashboard')) }}">
    <i class="fas fa-tachometer-alt w-5"></i><span>Dashboard</span>
</a>

@if($user->teacher_id)
    <a href="{{ route('teacher.timetable') }}" class="{{ $link(request()->routeIs('teacher.timetable')) }}">
        <i class="fas fa-calendar-week w-5"></i><span>My Timetable</span>
    </a>
    <a href="{{ route('teacher.profile.edit') }}" class="{{ $link(request()->routeIs('teacher.profile.*')) }}">
        <i class="fas fa-id-badge w-5"></i><span>My Profile</span>
    </a>
@endif

{{-- Pupils --}}
@canany(['manage-students', 'view-student-directory', 'manage-classes'])
    <p class="{{ $heading }}" x-show="!sidebarCollapsed">Pupils</p>
    @can('manage-students')
        <a href="{{ route('students.index') }}" class="{{ $link(request()->routeIs('students.*') && !request()->routeIs('students.report-card')) }}">
            <i class="fas fa-user-graduate w-5"></i><span>Pupils</span>
        </a>
    @else
        @can('view-student-directory')
            <a href="{{ route('student-directory.index') }}" class="{{ $link(request()->routeIs('student-directory.*')) }}">
                <i class="fas fa-user-graduate w-5"></i><span>Pupils</span>
            </a>
        @endcan
    @endcan
    @can('manage-classes')
        <a href="{{ route('class-groups.index') }}" class="{{ $link(request()->routeIs('class-groups.*')) }}">
            <i class="fas fa-chalkboard w-5"></i><span>Classes</span>
        </a>
        <a href="{{ route('promotions.index') }}" class="{{ $link(request()->routeIs('promotions.*')) }}">
            <i class="fas fa-level-up-alt w-5"></i><span>Promote Pupils</span>
        </a>
    @endcan
@endcanany

{{-- Fees --}}
@can('manage-fees')
    <p class="{{ $heading }}" x-show="!sidebarCollapsed">School Fees</p>
    <a href="{{ route('fees.index') }}" class="{{ $link(request()->routeIs('fees.index') || request()->routeIs('fees.show') || request()->routeIs('fees.receipt')) }}">
        <i class="fas fa-cash-register w-5"></i><span>Receive Fees</span>
    </a>
    <a href="{{ route('fee-structures.index') }}" class="{{ $link(request()->routeIs('fee-structures.*')) }}">
        <i class="fas fa-list-ol w-5"></i><span>Fee Schedule</span>
    </a>
    <a href="{{ route('fees.billing') }}" class="{{ $link(request()->routeIs('fees.billing')) }}">
        <i class="fas fa-file-invoice w-5"></i><span>Bill a Term</span>
    </a>
    <a href="{{ route('fees.charges.index') }}" class="{{ $link(request()->routeIs('fees.charges.*')) }}">
        <i class="fas fa-tags w-5"></i><span>Billed Items</span>
    </a>
    <a href="{{ route('fees.arrears.index') }}" class="{{ $link(request()->routeIs('fees.arrears.*')) }}">
        <i class="fas fa-history w-5"></i><span>Opening Balances</span>
    </a>
    <a href="{{ route('fees.report') }}" class="{{ $link(request()->routeIs('fees.report*')) }}">
        <i class="fas fa-chart-pie w-5"></i><span>Collection Report</span>
    </a>
@endcan

{{-- Terminal reports --}}
@canany(['enter-scores', 'manage-results', 'view-results', 'manage-term-reports'])
    <p class="{{ $heading }}" x-show="!sidebarCollapsed">Terminal Reports</p>
    @canany(['enter-scores', 'manage-results'])
        <a href="{{ route('scores.index') }}" class="{{ $link(request()->routeIs('scores.*')) }}">
            <i class="fas fa-pen-alt w-5"></i><span>Enter Scores</span>
        </a>
    @endcanany
    @can('manage-term-reports')
        <a href="{{ route('term-reports.index') }}" class="{{ $link(request()->routeIs('term-reports.*')) }}">
            <i class="fas fa-comment-dots w-5"></i><span>Attendance & Remarks</span>
        </a>
    @endcan
    <a href="{{ route('report-cards.bulk-form') }}" class="{{ $link(request()->routeIs('report-cards.*') || request()->routeIs('students.report-card')) }}">
        <i class="fas fa-print w-5"></i><span>Print Reports</span>
    </a>
    @canany(['manage-results', 'view-results'])
        <a href="{{ route('results.index') }}" class="{{ $link(request()->routeIs('results.*')) }}">
            <i class="fas fa-poll w-5"></i><span>All Results</span>
        </a>
    @endcanany
@endcanany

{{-- Academics --}}
@canany(['manage-subjects', 'manage-semesters', 'manage-timetable', 'manage-quizzes', 'manage-assignments', 'view-reports'])
    <p class="{{ $heading }}" x-show="!sidebarCollapsed">Academics</p>
    @can('manage-semesters')
        <a href="{{ route('semesters.index') }}" class="{{ $link(request()->routeIs('semesters.*')) }}">
            <i class="fas fa-calendar-alt w-5"></i><span>Terms</span>
        </a>
    @endcan
    @can('manage-subjects')
        <a href="{{ route('subjects.index') }}" class="{{ $link(request()->routeIs('subjects.*')) }}">
            <i class="fas fa-book w-5"></i><span>Subjects</span>
        </a>
        <a href="{{ route('teachers.index') }}" class="{{ $link(request()->routeIs('teachers.*')) }}">
            <i class="fas fa-chalkboard-teacher w-5"></i><span>Teachers</span>
        </a>
    @endcan
    @can('manage-timetable')
        <a href="{{ route('timetable.index') }}" class="{{ $link(request()->routeIs('timetable.*')) }}">
            <i class="fas fa-clock w-5"></i><span>Timetable</span>
        </a>
    @endcan
    @can('manage-quizzes')
        <a href="{{ route('quizzes.index') }}" class="{{ $link(request()->routeIs('quizzes.*')) }}">
            <i class="fas fa-question-circle w-5"></i><span>Quizzes</span>
        </a>
    @endcan
    @can('manage-assignments')
        <a href="{{ route('assignments.index') }}" class="{{ $link(request()->routeIs('assignments.*')) }}">
            <i class="fas fa-tasks w-5"></i><span>Assignments</span>
        </a>
    @endcan
    @can('view-reports')
        <a href="{{ route('reports.index') }}" class="{{ $link(request()->routeIs('reports.*') || request()->routeIs('grade-distribution.*')) }}">
            <i class="fas fa-chart-bar w-5"></i><span>Performance Reports</span>
        </a>
    @endcan
@endcanany

{{-- Communication --}}
@can('send-sms')
    <p class="{{ $heading }}" x-show="!sidebarCollapsed">Parents</p>
    <div x-data="{ open: {{ request()->routeIs('sms.*') ? 'true' : 'false' }} }">
        <button @click="open = !open" class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-gray-600 hover:bg-primary-50 hover:text-primary-600 rounded-lg {{ request()->routeIs('sms.*') ? 'bg-primary-50 text-primary-600 font-medium' : '' }}">
            <span class="flex items-center gap-3"><i class="fas fa-sms w-5"></i><span>SMS to Parents</span></span>
            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>
        <div x-show="open" x-cloak class="pl-8 space-y-1 mt-1">
            <a href="{{ route('sms.index') }}" class="block px-4 py-2 text-sm text-gray-600 hover:text-primary-600 rounded-lg {{ request()->routeIs('sms.index') ? 'text-primary-600 font-medium' : '' }}">Send SMS</a>
            <a href="{{ route('sms.parent-notifications') }}" class="block px-4 py-2 text-sm text-gray-600 hover:text-primary-600 rounded-lg {{ request()->routeIs('sms.parent-notifications') ? 'text-primary-600 font-medium' : '' }}">Results & Fee SMS Log</a>
            <a href="{{ route('sms.history') }}" class="block px-4 py-2 text-sm text-gray-600 hover:text-primary-600 rounded-lg {{ request()->routeIs('sms.history*') ? 'text-primary-600 font-medium' : '' }}">History</a>
            <a href="{{ route('sms.templates.index') }}" class="block px-4 py-2 text-sm text-gray-600 hover:text-primary-600 rounded-lg {{ request()->routeIs('sms.templates.*') ? 'text-primary-600 font-medium' : '' }}">Templates</a>
        </div>
    </div>
@endcan

{{-- Administration --}}
@canany(['manage-settings', 'manage-users', 'manage-roles', 'view-activity-logs'])
    <p class="{{ $heading }}" x-show="!sidebarCollapsed">Administration</p>
    @can('manage-settings')
        <a href="{{ route('settings.index') }}" class="{{ $link(request()->routeIs('settings.*')) }}">
            <i class="fas fa-cog w-5"></i><span>Settings</span>
        </a>
    @endcan
    @can('manage-users')
        <a href="{{ route('users.index') }}" class="{{ $link(request()->routeIs('users.*')) }}">
            <i class="fas fa-users-cog w-5"></i><span>Staff Accounts</span>
        </a>
    @endcan
    @can('manage-roles')
        <a href="{{ route('roles.index') }}" class="{{ $link(request()->routeIs('roles.*')) }}">
            <i class="fas fa-user-shield w-5"></i><span>Roles & Permissions</span>
        </a>
    @endcan
    @can('view-activity-logs')
        <a href="{{ route('activity-logs.index') }}" class="{{ $link(request()->routeIs('activity-logs.*')) }}">
            <i class="fas fa-history w-5"></i><span>Activity Logs</span>
        </a>
    @endcan
@endcanany
