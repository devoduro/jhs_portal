<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Enter Scores') }}</h2>
            @if($classGroup && $term)
                <div class="flex gap-2">
                    <x-button href="{{ route('scores.broadsheet', ['classGroup' => $classGroup, 'semester_id' => $term->id]) }}" variant="secondary" icon="fas fa-table">Broadsheet</x-button>
                    <x-button href="{{ route('term-reports.index', ['class_group_id' => $classGroup->id, 'semester_id' => $term->id]) }}" variant="secondary" icon="fas fa-comment-dots">Attendance & Remarks</x-button>
                    <x-button href="{{ route('report-cards.bulk-print', ['class_group_id' => $classGroup->id, 'semester_id' => $term->id]) }}" icon="fas fa-print" target="_blank">Print Reports</x-button>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('scores.index') }}" class="bg-white shadow-sm rounded-lg p-6 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                    <select name="semester_id" class="rounded-lg border-gray-300 text-sm">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_group_id" class="rounded-lg border-gray-300 text-sm" required>
                        <option value="">Choose a class...</option>
                        @foreach($classGroups as $c)
                            <option value="{{ $c->id }}" @selected($classGroup && $classGroup->id === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" icon="fas fa-arrow-right">Open</x-button>
            </form>

            @if($classGroups->isEmpty())
                <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 text-sm">
                    You have no classes yet. Ask the administrator to make you class teacher of a class or assign you to subjects.
                </div>
            @endif

            @if($classGroup && $term)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h3 class="font-semibold text-gray-900">{{ $classGroup->name }} — {{ $term->full_name }}</h3>
                        <p class="text-sm text-gray-500">Class teacher: {{ $classGroup->classTeacher->name ?? 'Not assigned' }}. Choose a subject to enter scores.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
                        @forelse($subjects as $subject)
                            @php
                                $p = $progress[$subject->id] ?? ['entered' => 0, 'roll' => 0];
                                $done = $p['roll'] > 0 && $p['entered'] >= $p['roll'];
                            @endphp
                            <a href="{{ route('scores.sheet', ['classGroup' => $classGroup, 'subject' => $subject, 'semester_id' => $term->id]) }}"
                               class="block border rounded-lg p-4 hover:border-primary-400 hover:shadow {{ $done ? 'border-green-300 bg-green-50' : 'border-gray-200' }}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $subject->title }}</p>
                                        <p class="text-xs text-gray-500">{{ $subject->code }}{{ $subject->is_core ? ' · Core' : '' }}</p>
                                    </div>
                                    <i class="fas {{ $done ? 'fa-check-circle text-green-600' : 'fa-pen text-gray-400' }}"></i>
                                </div>
                                <div class="mt-3 h-2 bg-gray-100 rounded">
                                    <div class="h-2 rounded {{ $done ? 'bg-green-500' : 'bg-primary-500' }}" style="width: {{ $p['roll'] ? min(100, round($p['entered'] / $p['roll'] * 100)) : 0 }}%"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ $p['entered'] }} of {{ $p['roll'] }} pupils scored</p>
                            </a>
                        @empty
                            <p class="text-sm text-gray-500">No subjects set up for {{ \App\Support\FormLabel::label($classGroup->level) }}. Add them under Subjects.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
