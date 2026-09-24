<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Confirm Promotion') }}
        </h2>
    </x-slot>

    @php
        $shsLabel = fn ($lvl) => \App\Support\FormLabel::label((int) $lvl);
    @endphp

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-6 p-4 bg-amber-50 border-l-4 border-amber-400 text-amber-800 text-sm">
                        @if($isTerminal)
                            <p>This will mark <strong>{{ $students->count() }}</strong> student(s) as <strong>graduated</strong> from {{ $shsLabel($level) }} on the <strong>{{ $programme->name }}</strong> programme. {{ $shsLabel($level) }} is this programme's final level, so there is no further level to promote them to.</p>
                        @else
                            <p>This will move <strong>{{ $students->count() }}</strong> student(s) from <strong>{{ $shsLabel($level) }}</strong> to <strong>{{ $shsLabel($targetLevel) }}</strong> on the <strong>{{ $programme->name }}</strong> programme.</p>
                        @endif
                        @if($isTerminal)
                            <p class="mt-2">Their current class assignment will be cleared. Historical results, continuous assessment scores, registrations and arrears are not affected.</p>
                        @else
                            <p class="mt-2">Students whose current class has a matching {{ $shsLabel($targetLevel) }} class (same name with the form number updated, e.g. "1S12" &rarr; "2S12") will be carried into it automatically. Anyone without a match will be left unassigned — reassign them via <strong>Classes &gt; Assign Students</strong>. Historical results, continuous assessment scores, registrations and arrears are not affected.</p>
                        @endif
                    </div>

                    <div class="overflow-x-auto mb-6">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Index Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Class</th>
                                    @if(!$isTerminal)
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Class</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($students as $student)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->index_number }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->full_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->classGroup->name ?? 'Unassigned' }}</td>
                                        @if(!$isTerminal)
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($student->predicted_class_group)
                                                    <span class="text-green-700 font-medium">{{ $student->predicted_class_group->name }}</span>
                                                @else
                                                    <span class="text-amber-600">Unassigned (no match)</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-3 items-end">
                        <a href="{{ route('promotions.index') }}" class="bg-gray-200 text-gray-700 rounded-md px-4 py-2 text-sm hover:bg-gray-300">
                            Cancel
                        </a>
                        <form method="POST" action="{{ route('promotions.store') }}" class="flex items-end gap-3" onsubmit="return confirm('{{ $isTerminal ? 'Mark these students as graduated? This cannot be undone from this screen.' : 'Promote these students to ' . $shsLabel($targetLevel) . '? This cannot be undone from this screen.' }}')">
                            @csrf
                            <input type="hidden" name="programme_id" value="{{ $programme->id }}">
                            <input type="hidden" name="level" value="{{ $level }}">
                            @if($isTerminal)
                                <div>
                                    <label for="graduation_year" class="block text-sm font-medium text-gray-700">Graduation Year</label>
                                    <input type="number" name="graduation_year" id="graduation_year" value="{{ old('graduation_year', $defaultGraduationYear) }}" min="2000" max="2100" required class="mt-1 block w-32 pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                </div>
                            @endif
                            <button type="submit" class="bg-red-600 text-white rounded-md px-4 py-2 text-sm hover:bg-red-700">
                                {{ $isTerminal ? 'Confirm Graduation' : 'Confirm Promotion' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
