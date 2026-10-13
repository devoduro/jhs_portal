<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Settings') }}</h2>
    </x-slot>

    @php
        // Each card: colour, icon, title, what it controls, where it goes, and the quick facts shown on it.
        $groups = [
            'School setup' => [
                ['blue', 'fa-calendar-alt', 'Academic Years & Terms', 'Create academic years and their three terms, and choose the current term.', route('settings.academic-years'), $currentAcademicYear ? $currentAcademicYear->name : 'No current year'],
                ['green', 'fa-school', 'School & Report Settings', 'Classes offered (KG, Primary, JHS), default class/exam split, admission numbers and what shows on terminal reports.', route('settings.school'), $classCount . ' classes'],
                ['indigo', 'fa-university', 'Institution Profile', 'School name, logo, address and contact details printed on reports and receipts.', route('settings.institution'), $institutionName ?: 'Not set'],
            ],
            'Assessment' => [
                ['purple', 'fa-table-list', 'Score Sheets & Continuous Assessment', 'Design the columns teachers score on for each class - exercises, tests, projects, exam - and the marks each carries.', route('assessment-settings.index'), $subjectCount . ' subjects'],
                ['orange', 'fa-chart-line', 'Grade Schemes', 'Grading scales for Primary (NaCCA) and JHS (BECE 1-9) with remarks.', route('settings.grade-schemes'), $gradeSchemes->count() . ' scheme(s)'],
            ],
            'System' => [
                ['teal', 'fa-sliders', 'System Settings', 'General system preferences, SMS and session options.', route('settings.system'), 'Preferences'],
                ['amber', 'fa-database', 'Database Backup', 'Create, download and restore database backups.', route('settings.backup'), $lastBackup ? 'Last: ' . $lastBackup->diffForHumans() : 'No backup yet'],
            ],
        ];

        $tones = [
            'blue'   => ['bar' => 'from-[#42a5dc] to-[#2c8fc6]', 'soft' => 'bg-[#42a5dc]/10 text-[#2c8fc6]', 'btn' => 'bg-[#42a5dc] hover:bg-[#2c8fc6]'],
            'green'  => ['bar' => 'from-[#8cc152] to-[#74a83b]', 'soft' => 'bg-[#8cc152]/15 text-[#5f8f2a]', 'btn' => 'bg-[#8cc152] hover:bg-[#74a83b]'],
            'indigo' => ['bar' => 'from-indigo-500 to-indigo-700', 'soft' => 'bg-indigo-50 text-indigo-600', 'btn' => 'bg-indigo-500 hover:bg-indigo-600'],
            'purple' => ['bar' => 'from-[#967adc] to-[#7d62c4]', 'soft' => 'bg-[#967adc]/10 text-[#7d62c4]', 'btn' => 'bg-[#967adc] hover:bg-[#7d62c4]'],
            'orange' => ['bar' => 'from-[#f5922f] to-[#de7a14]', 'soft' => 'bg-[#f5922f]/10 text-[#de7a14]', 'btn' => 'bg-[#f5922f] hover:bg-[#de7a14]'],
            'teal'   => ['bar' => 'from-teal-400 to-teal-600', 'soft' => 'bg-teal-50 text-teal-600', 'btn' => 'bg-teal-500 hover:bg-teal-600'],
            'amber'  => ['bar' => 'from-amber-400 to-amber-600', 'soft' => 'bg-amber-50 text-amber-600', 'btn' => 'bg-amber-500 hover:bg-amber-600'],
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <x-dashboard-hero title="Settings" :eyebrow="$institutionName ?: 'Your school'"
                subtitle="Set up the school year, how scores and grades work, how reports look, and keep your data safe.">
                <x-slot:chips>
                    <span><i class="fas fa-calendar-check"></i> {{ $currentAcademicYear->name ?? 'No current year' }}</span>
                    <span><i class="fas fa-book-open"></i> {{ $currentSemester->name ?? 'No current term' }}</span>
                    <span><i class="fas fa-user-graduate"></i> {{ number_format($totalStudents) }} active pupils</span>
                </x-slot:chips>
                <a class="primary" href="{{ route('settings.academic-years.create') }}"><i class="fas fa-calendar-plus text-[#42a5dc]"></i> New Academic Year</a>
                <a href="{{ route('settings.grade-schemes.create') }}"><i class="fas fa-plus-circle"></i> New Grade Scheme</a>
            </x-dashboard-hero>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-stat-tile color="blue" icon="fa-calendar-alt" title="Academic Year" :sub="$currentAcademicYear ? 'Current' : 'Set one up'" :value="$currentAcademicYear->name ?? '—'" :href="route('settings.academic-years')" />
                <x-stat-tile color="green" icon="fa-book-open" title="Current Term" :sub="$currentSemester?->academicYear?->name" :value="$currentSemester->name ?? '—'" :href="route('settings.academic-years')" />
                <x-stat-tile color="purple" icon="fa-user-graduate" title="Active Pupils" :sub="$classCount . ' classes'" :value="number_format($totalStudents)" />
                <x-stat-tile :color="$lastBackup ? 'orange' : 'red'" icon="fa-database" title="Last Backup" :sub="$lastBackup ? $lastBackup->format('d M Y H:i') : 'Create one now'" :value="$lastBackup ? $lastBackup->diffForHumans(null, true) : 'Never'" :href="route('settings.backup')" />
            </div>

            @foreach($groups as $heading => $cards)
                <section>
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $heading }}</h3>
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($cards as [$color, $icon, $title, $text, $url, $fact])
                            @php $t = $tones[$color]; @endphp
                            <a href="{{ $url }}" class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100 transition hover:-translate-y-0.5 hover:shadow-md">
                                <div class="h-1.5 bg-gradient-to-r {{ $t['bar'] }}"></div>
                                <div class="flex flex-1 flex-col p-5">
                                    <div class="mb-4 flex items-center gap-4">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $t['soft'] }}"><i class="fas {{ $icon }} text-xl"></i></span>
                                        <h4 class="text-base font-semibold leading-tight text-gray-900">{{ $title }}</h4>
                                    </div>
                                    <p class="flex-1 text-sm text-gray-600">{{ $text }}</p>
                                    <div class="mt-5 flex items-center justify-between">
                                        <span class="truncate rounded-full px-2.5 py-1 text-xs font-medium {{ $t['soft'] }}">{{ $fact }}</span>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-semibold text-white transition {{ $t['btn'] }}">Manage <i class="fas fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i></span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section>
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Quick actions</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <form action="{{ route('settings.backup.create') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-gray-100 transition hover:shadow-md">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><i class="fas fa-database"></i></span>
                            <span><span class="block text-sm font-semibold text-gray-900">Create backup</span><span class="block text-xs text-gray-500">Save a copy of the database now</span></span>
                        </button>
                    </form>
                    <a href="{{ route('settings.academic-years.create') }}" class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100 transition hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#42a5dc]/10 text-[#2c8fc6]"><i class="fas fa-calendar-plus"></i></span>
                        <span><span class="block text-sm font-semibold text-gray-900">New academic year</span><span class="block text-xs text-gray-500">Start a year with its three terms</span></span>
                    </a>
                    <a href="{{ route('settings.grade-schemes.create') }}" class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100 transition hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#f5922f]/10 text-[#de7a14]"><i class="fas fa-plus-circle"></i></span>
                        <span><span class="block text-sm font-semibold text-gray-900">New grade scheme</span><span class="block text-xs text-gray-500">Add a grading scale with remarks</span></span>
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
