<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Print Terminal Reports') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-1">1. Choose the term and class</h3>
                <p class="text-sm text-gray-500 mb-4">Before printing, make sure all subject scores are entered (Enter Scores) and the class teacher has filled in attendance and remarks.</p>

                <form method="GET" action="{{ route('report-cards.bulk-form') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                        <select name="semester_id" class="block w-full rounded-lg border-gray-300 text-sm">
                            @foreach($semesters as $t)
                                <option value="{{ $t->id }}" @selected($selectedTerm && $selectedTerm->id === $t->id)>{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select name="class_group_id" class="block w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="">Select a class&hellip;</option>
                            @foreach($classGroups as $group)
                                <option value="{{ $group->id }}" @selected((int) request('class_group_id') === $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-button type="submit" variant="secondary" icon="fas fa-users">Load Class</x-button>
                </form>
            </div>

            @if(request('class_group_id') && $students->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">2. Print</h3>

                    <form method="GET" action="{{ route('report-cards.bulk-print') }}" target="_blank">
                        <input type="hidden" name="class_group_id" value="{{ request('class_group_id') }}">
                        <input type="hidden" name="semester_id" value="{{ $selectedTerm?->id }}">

                        <div class="flex gap-4 mb-3">
                            <button type="button" onclick="document.querySelectorAll('.rc-check').forEach(c => c.checked = true)" class="text-sm text-primary-600 hover:underline">Select all</button>
                            <button type="button" onclick="document.querySelectorAll('.rc-check').forEach(c => c.checked = false)" class="text-sm text-primary-600 hover:underline">Deselect all</button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-md mb-4">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 w-10"></th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pupil</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Admission No.</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($students as $student)
                                        <tr>
                                            <td class="px-4 py-2"><input type="checkbox" class="rc-check rounded border-gray-300" name="student_ids[]" value="{{ $student->id }}" checked></td>
                                            <td class="px-4 py-2 text-gray-900">{{ $student->full_name }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $student->index_number }}</td>
                                            <td class="px-4 py-2 text-right">
                                                <a href="{{ route('students.report-card', ['student' => $student, 'semester_id' => $selectedTerm?->id]) }}" target="_blank" class="text-primary-600 text-xs hover:underline">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-button type="submit" icon="fas fa-print">Print Selected</x-button>
                            <x-button href="{{ route('report-cards.bulk-print', ['class_group_id' => request('class_group_id'), 'semester_id' => $selectedTerm?->id]) }}" target="_blank" variant="secondary" icon="fas fa-print">Print Whole Class</x-button>
                            <x-button href="{{ route('scores.broadsheet', ['classGroup' => request('class_group_id'), 'semester_id' => $selectedTerm?->id]) }}" target="_blank" variant="secondary" icon="fas fa-table">Broadsheet</x-button>
                        </div>
                    </form>
                </div>
            @elseif(request('class_group_id'))
                <div class="bg-white shadow-sm rounded-lg p-6 text-center text-gray-500">No pupils are in this class for the chosen term.</div>
            @endif
        </div>
    </div>
</x-app-layout>
