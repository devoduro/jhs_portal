<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('All Results') }}</h2>
            @canany(['enter-scores', 'manage-results'])
                <x-button href="{{ route('scores.index', array_filter(['class_group_id' => request('class_group_id'), 'semester_id' => request('semester_id')])) }}" icon="fas fa-pen-to-square">Enter Scores</x-button>
            @endcanany
        </div>
    </x-slot>

    @php $fmt = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format((float) $n, 1), '0'), '.'); @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('results.index') }}" class="grid grid-cols-2 gap-3 rounded-lg bg-white p-4 shadow-sm md:grid-cols-6 md:items-end">
                <div class="col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Pupil</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or admission number" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Term</label>
                    <select name="semester_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All terms</option>
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected(request('semester_id') == $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Class</label>
                    <select name="class_group_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All classes</option>
                        @foreach($classGroups as $c)
                            <option value="{{ $c->id }}" @selected(request('class_group_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Subject</label>
                    <select name="subject_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All subjects</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}" @selected(request('subject_id') == $s->id)>{{ $s->title }} ({{ \App\Support\FormLabel::label($s->level) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-button type="submit" icon="fas fa-filter">Filter</x-button>
                    <x-button href="{{ route('results.index') }}" variant="secondary">Reset</x-button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b px-4 py-3 text-sm text-gray-600">{{ $results->total() }} score(s)</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Pupil</th>
                                <th class="px-4 py-3 text-left font-medium">Subject</th>
                                <th class="px-4 py-3 text-left font-medium">Class / Term</th>
                                <th class="px-3 py-3 text-center font-medium">Class</th>
                                <th class="px-3 py-3 text-center font-medium">Exam</th>
                                <th class="px-3 py-3 text-center font-medium">Total</th>
                                <th class="px-3 py-3 text-center font-medium">Grade</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($results as $result)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('results.show', $result) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $result->student->full_name ?? '—' }}</a>
                                        <div class="text-xs text-gray-500">{{ $result->student->index_number ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $result->subject->title ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $result->classGroup->name ?? '—' }}
                                        <div class="text-xs text-gray-400">{{ $result->semester->full_name ?? '' }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-center">{{ $fmt($result->class_score) }}</td>
                                    <td class="px-3 py-3 text-center">{{ $fmt($result->exam_score) }}</td>
                                    <td class="px-3 py-3 text-center font-semibold">{{ $fmt($result->score) }}</td>
                                    <td class="px-3 py-3 text-center"><span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700" title="{{ $result->remark }}">{{ $result->grade }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right space-x-3">
                                        <a href="{{ route('results.show', $result) }}" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                                        @canany(['enter-scores', 'manage-results'])
                                            <a href="{{ route('results.edit', $result) }}" class="text-primary-600 hover:text-primary-900" title="Edit on score sheet"><i class="fas fa-pen-to-square"></i></a>
                                        @endcanany
                                        @can('manage-results')
                                            <form action="{{ route('results.destroy', $result) }}" method="POST" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Delete this score? It will disappear from the terminal report.')"><i class="fas fa-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">No scores match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3">{{ $results->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
