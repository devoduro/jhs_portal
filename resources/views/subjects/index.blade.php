<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Subjects') }}</h2>
            @can('manage-subjects')
                <x-button href="{{ route('subjects.create') }}" icon="fas fa-plus">Add Subject</x-button>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('subjects.index') }}" class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Subject or code" class="rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Class level</label>
                    <select name="level" class="rounded-lg border-gray-300 text-sm">
                        <option value="">All levels</option>
                        @foreach(\App\Support\FormLabel::options() as $level => $label)
                            <option value="{{ $level }}" @selected(request('level') == $level)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" icon="fas fa-filter">Filter</x-button>
            </form>

            <p class="text-sm text-gray-500">Core subjects (marked <span class="px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-800">Core</span>) are always counted in the JHS aggregate: English, Mathematics, Science and Social Studies.</p>

            @forelse($subjects as $level => $levelSubjects)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <h3 class="px-6 py-3 bg-gray-50 border-b font-semibold text-gray-900">{{ \App\Support\FormLabel::label($level) }} <span class="text-sm text-gray-500 font-normal">({{ $levelSubjects->count() }} subjects)</span></h3>
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        @foreach($levelSubjects as $subject)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 w-32 text-gray-500">{{ $subject->code }}</td>
                                <td class="px-6 py-3 font-medium text-gray-900">
                                    {{ $subject->title }}
                                    @if($subject->is_core)<span class="ml-2 px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-800">Core</span>@endif
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $subject->teachers->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap space-x-3">
                                    <a href="{{ route('subjects.results', $subject) }}" class="text-gray-600 hover:text-gray-900" title="Scores"><i class="fas fa-poll"></i></a>
                                    @can('manage-subjects')
                                        <a href="{{ route('subjects.edit', $subject) }}" class="text-indigo-600 hover:text-indigo-900" title="Edit"><i class="fas fa-edit"></i></a>
                                        <form action="{{ route('subjects.destroy', $subject) }}" method="POST" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Delete {{ $subject->title }} ({{ $subject->code }})?')"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @empty
                <div class="bg-white shadow-sm rounded-lg p-10 text-center text-gray-400">No subjects found.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
