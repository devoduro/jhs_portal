@php $user = auth()->user(); @endphp

<x-nav-link href="{{ route('dashboard') }}" icon="fa-tachometer-alt" :active="request()->routeIs('dashboard') || request()->routeIs('admin.dashboard')">
    Dashboard
</x-nav-link>

{{-- ── My Workspace: a teacher's own pages ─────────────────────────────── --}}
@if($user->teacher_id)
    <x-nav-heading>My Workspace</x-nav-heading>
    <x-nav-link href="{{ route('teacher.timetable') }}" icon="fa-calendar-week" :active="request()->routeIs('teacher.timetable*')">
        My Timetable
    </x-nav-link>
    <x-nav-link href="{{ route('teacher.profile.edit') }}" icon="fa-id-badge" :active="request()->routeIs('teacher.profile.*')">
        My Profile
    </x-nav-link>
@endif

{{-- ── Pupils ─────────────────────────────────────────────────────────── --}}
@canany(['manage-students', 'view-student-directory', 'manage-classes'])
    <x-nav-heading>Pupils</x-nav-heading>
@endcanany
@can('manage-students')
    <x-nav-link href="{{ route('students.index') }}" icon="fa-user-graduate" :active="request()->routeIs('students.*') && !request()->routeIs('students.report-card') && !request()->routeIs('students.transcript')">
        Pupils
    </x-nav-link>
@else
    @can('view-student-directory')
        <x-nav-link href="{{ route('student-directory.index') }}" icon="fa-user-graduate" :active="request()->routeIs('student-directory.*')">
            Pupils
        </x-nav-link>
    @endcan
@endcan
@can('manage-classes')
    <x-nav-link href="{{ route('class-groups.index') }}" icon="fa-chalkboard" :active="request()->routeIs('class-groups.*')">
        Classes
    </x-nav-link>
    <x-nav-link href="{{ route('promotions.index') }}" icon="fa-level-up-alt" :active="request()->routeIs('promotions.*')">
        Promote Pupils
    </x-nav-link>
@endcan

{{-- ── School Fees ────────────────────────────────────────────────────── --}}
@can('manage-fees')
    <x-nav-heading>School Fees</x-nav-heading>
    <x-nav-link href="{{ route('fees.index') }}" icon="fa-cash-register" :active="request()->routeIs('fees.index') || request()->routeIs('fees.show') || request()->routeIs('fees.receipt')">
        Receive Fees
    </x-nav-link>
    <x-nav-link href="{{ route('fees.report') }}" icon="fa-chart-pie" :active="request()->routeIs('fees.report*')">
        Collection Report
    </x-nav-link>
    <x-nav-group label="Fee Setup" icon="fa-sliders"
        :active="request()->routeIs('fee-structures.*') || request()->routeIs('fees.billing') || request()->routeIs('fees.charges.*') || request()->routeIs('fees.arrears.*')">
        <x-nav-sublink href="{{ route('fee-structures.index') }}" :active="request()->routeIs('fee-structures.*')">Fee Schedule</x-nav-sublink>
        <x-nav-sublink href="{{ route('fees.billing') }}" :active="request()->routeIs('fees.billing')">Bill a Term</x-nav-sublink>
        <x-nav-sublink href="{{ route('fees.charges.index') }}" :active="request()->routeIs('fees.charges.*')">Billed Items</x-nav-sublink>
        <x-nav-sublink href="{{ route('fees.arrears.index') }}" :active="request()->routeIs('fees.arrears.*')">Opening Balances</x-nav-sublink>
    </x-nav-group>
@endcan

{{-- ── Terminal Reports ───────────────────────────────────────────────── --}}
@canany(['enter-scores', 'manage-results', 'view-results', 'manage-term-reports'])
    <x-nav-heading>Terminal Reports</x-nav-heading>
    @canany(['enter-scores', 'manage-results'])
        <x-nav-link href="{{ route('scores.index') }}" icon="fa-pen-to-square" :active="request()->routeIs('scores.*')">
            Enter Scores
        </x-nav-link>
    @endcanany
    @can('manage-term-reports')
        <x-nav-link href="{{ route('term-reports.index') }}" icon="fa-comment-dots" :active="request()->routeIs('term-reports.*')">
            Attendance & Remarks
        </x-nav-link>
    @endcan
    <x-nav-link href="{{ route('report-cards.bulk-form') }}" icon="fa-print" :active="request()->routeIs('report-cards.*') || request()->routeIs('students.report-card')">
        Print Reports
    </x-nav-link>
    @canany(['manage-results', 'manage-students', 'view-reports'])
        <x-nav-link href="{{ route('transcripts.index') }}" icon="fa-scroll" :active="request()->routeIs('transcripts.*') || request()->routeIs('students.transcript')">
            Transcripts
        </x-nav-link>
    @endcanany
    @canany(['manage-results', 'view-results'])
        <x-nav-link href="{{ route('results.index') }}" icon="fa-poll" :active="request()->routeIs('results.*')">
            All Results
        </x-nav-link>
    @endcanany
@endcanany

{{-- ── Academics ──────────────────────────────────────────────────────── --}}
@canany(['manage-subjects', 'manage-semesters', 'manage-timetable', 'manage-quizzes', 'manage-assignments', 'view-reports'])
    <x-nav-heading>Academics</x-nav-heading>
    @can('manage-semesters')
        <x-nav-link href="{{ route('semesters.index') }}" icon="fa-calendar-alt" :active="request()->routeIs('semesters.*')">
            Terms
        </x-nav-link>
    @endcan
    @can('manage-subjects')
        <x-nav-link href="{{ route('subjects.index') }}" icon="fa-book" :active="request()->routeIs('subjects.*')">
            Subjects
        </x-nav-link>
        <x-nav-link href="{{ route('teachers.index') }}" icon="fa-chalkboard-teacher" :active="request()->routeIs('teachers.*') || request()->routeIs('departments.*')">
            Teachers
        </x-nav-link>
    @endcan
    @canany(['manage-timetable', 'manage-quizzes', 'manage-assignments'])
        <x-nav-group label="Teaching" icon="fa-person-chalkboard"
            :active="request()->routeIs('timetable.*') || request()->routeIs('venues.*') || request()->routeIs('quizzes.*') || request()->routeIs('assignments.*')">
            @can('manage-timetable')
                <x-nav-sublink href="{{ route('timetable.index') }}" :active="request()->routeIs('timetable.*') || request()->routeIs('venues.*')">Timetable</x-nav-sublink>
            @endcan
            @can('manage-quizzes')
                <x-nav-sublink href="{{ route('quizzes.index') }}" :active="request()->routeIs('quizzes.*')">Quizzes</x-nav-sublink>
            @endcan
            @can('manage-assignments')
                <x-nav-sublink href="{{ route('assignments.index') }}" :active="request()->routeIs('assignments.*')">Assignments</x-nav-sublink>
            @endcan
        </x-nav-group>
    @endcanany
    @can('view-reports')
        <x-nav-link href="{{ route('reports.index') }}" icon="fa-chart-bar" :active="request()->routeIs('reports.*')">
            Performance Reports
        </x-nav-link>
    @endcan
@endcanany

{{-- ── Parents ────────────────────────────────────────────────────────── --}}
@can('send-sms')
    <x-nav-heading>Parents</x-nav-heading>
    <x-nav-group label="SMS to Parents" icon="fa-sms" :active="request()->routeIs('sms.*')">
        <x-nav-sublink href="{{ route('sms.index') }}" :active="request()->routeIs('sms.index')">Send SMS</x-nav-sublink>
        <x-nav-sublink href="{{ route('sms.history') }}" :active="request()->routeIs('sms.history*')">Sent Messages</x-nav-sublink>
        <x-nav-sublink href="{{ route('sms.parent-notifications') }}" :active="request()->routeIs('sms.parent-notifications')">Automatic SMS Log</x-nav-sublink>
        <x-nav-sublink href="{{ route('sms.templates.index') }}" :active="request()->routeIs('sms.templates.*')">Templates</x-nav-sublink>
    </x-nav-group>
@endcan

{{-- ── Administration ─────────────────────────────────────────────────── --}}
@canany(['manage-settings', 'manage-users', 'manage-roles', 'view-activity-logs'])
    <x-nav-heading>Administration</x-nav-heading>
    @can('manage-settings')
        <x-nav-link href="{{ route('settings.index') }}" icon="fa-cog" :active="request()->routeIs('settings.*') || request()->routeIs('ca-score-settings.*')">
            Settings
        </x-nav-link>
    @endcan
    @can('manage-users')
        <x-nav-link href="{{ route('users.index') }}" icon="fa-users-cog" :active="request()->routeIs('users.*')">
            Staff Accounts
        </x-nav-link>
    @endcan
    @can('manage-roles')
        <x-nav-link href="{{ route('roles.index') }}" icon="fa-user-shield" :active="request()->routeIs('roles.*')">
            Roles & Permissions
        </x-nav-link>
    @endcan
    @can('view-activity-logs')
        <x-nav-link href="{{ route('activity-logs.index') }}" icon="fa-clock-rotate-left" :active="request()->routeIs('activity-logs.*')">
            Activity Logs
        </x-nav-link>
    @endcan
@endcanany
