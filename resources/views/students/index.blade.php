<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pupils') }}</h2>
            <div class="flex flex-wrap gap-2">
                <x-button href="{{ route('students.create', ['class_group_id' => request('class_group_id')]) }}" icon="fas fa-plus">Admit Pupil</x-button>
                <x-button href="{{ route('students.import.form') }}" variant="secondary" icon="fas fa-file-import">Import</x-button>
                <x-button href="{{ route('students.export.excel', request()->query()) }}" variant="secondary" icon="fas fa-file-excel">Export</x-button>
                <div class="relative" x-data="{ open: false }">
                    <x-button type="button" variant="secondary" icon="fas fa-key" @click="open = !open">Portal logins</x-button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-72 bg-white border rounded-lg shadow-lg z-30 p-3 space-y-3 text-sm">
                        <form method="POST" action="{{ route('students.bulk-create-accounts') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-3 py-2 rounded hover:bg-gray-50" onclick="return confirm('Create portal logins for every pupil who does not have one?')">
                                <i class="fas fa-user-plus mr-2 text-green-600"></i>Create logins for pupils without one
                            </button>
                        </form>
                        <form method="POST" action="{{ route('students.bulk-reset-passwords') }}" class="border-t pt-3 space-y-2" onsubmit="return confirm('Reset the password of EVERY pupil login?')">
                            @csrf
                            <label class="block text-xs text-gray-500">Reset all pupil passwords to:</label>
                            <input type="text" name="default_password" minlength="6" required class="w-full rounded border-gray-300 text-sm" placeholder="New default password">
                            <button type="submit" class="w-full px-3 py-2 rounded bg-yellow-500 text-white hover:bg-yellow-600">Reset all passwords</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('students.index') }}" class="bg-white shadow-sm rounded-lg p-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Pupil, admission no., parent or phone" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Class</label>
                    <select name="class_group_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All classes</option>
                        @foreach($classGroups as $classGroup)
                            <option value="{{ $classGroup->id }}" @selected(request('class_group_id') == $classGroup->id)>{{ $classGroup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Gender</label>
                    <select name="gender" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All</option>
                        <option value="Male" @selected(request('gender') === 'Male')>Boys</option>
                        <option value="Female" @selected(request('gender') === 'Female')>Girls</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="active" @selected(request('status', 'active') === 'active')>In school</option>
                        <option value="graduated" @selected(request('status') === 'graduated')>Completed</option>
                        <option value="withdrawn" @selected(request('status') === 'withdrawn')>Withdrawn</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-button type="submit" icon="fas fa-filter">Filter</x-button>
                    <x-button href="{{ route('students.index') }}" variant="secondary">Reset</x-button>
                </div>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b text-sm text-gray-600">{{ $students->total() }} pupil(s)</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pupil</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No.</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Parent / Guardian</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($students as $student)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-student-photo :student="$student" class="w-9 h-9 rounded-full object-cover" />
                                            <div>
                                                <a href="{{ route('students.show', $student) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $student->full_name }}</a>
                                                <div class="text-xs text-gray-500">{{ $student->gender === 'Male' ? 'Boy' : ($student->gender === 'Female' ? 'Girl' : '') }}{{ $student->date_of_birth ? ' · ' . $student->date_of_birth->age . ' yrs' : '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $student->index_number }}</td>
                                    <td class="px-4 py-3">
                                        @if($student->classGroup)
                                            {{ $student->classGroup->name }}
                                        @else
                                            <span class="text-orange-600">{{ \App\Support\FormLabel::label($student->level) }} (no class)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $student->emergency_contact_name ?: '—' }}
                                        <div class="text-xs text-gray-500">{{ $student->emergency_contact_phone }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                                        @can('manage-fees')
                                            <a href="{{ route('fees.show', $student) }}" class="text-green-600 hover:text-green-800" title="Fees"><i class="fas fa-cash-register"></i></a>
                                        @endcan
                                        <a href="{{ route('students.report-card', $student) }}" class="text-indigo-600 hover:text-indigo-800" title="Terminal report" target="_blank"><i class="fas fa-file-alt"></i></a>
                                        <a href="{{ route('students.edit', $student) }}" class="text-blue-600 hover:text-blue-800" title="Edit"><i class="fas fa-edit"></i></a>
                                        <form action="{{ route('students.destroy', $student) }}" method="POST" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800" title="Delete" onclick="return confirm('Permanently delete {{ addslashes($student->full_name) }} and all their scores and fee records? To keep records, edit the pupil and set status to Withdrawn instead.')"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No pupils found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3">{{ $students->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
