<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Performance Reports') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('reports.index') }}" class="bg-white shadow-sm rounded-lg p-4 flex items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Term</label>
                    <select name="semester_id" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if($classes->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <h3 class="px-6 py-4 border-b font-semibold text-gray-900">Class summary — {{ $term->full_name }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pupils scored</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Class average</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Averaging 50%+</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Top pupils</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Best / weakest subject</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($classes as $row)
                                    <tr class="align-top">
                                        <td class="px-4 py-3 font-medium">{{ $row['class']->name }}<div class="text-xs text-gray-500">{{ $row['class']->classTeacher->name ?? '' }}</div></td>
                                        <td class="px-4 py-3 text-right">{{ $row['scored'] }} / {{ $row['roll'] }}</td>
                                        <td class="px-4 py-3 text-right font-semibold">{{ $row['average'] !== null ? $row['average'] . '%' : '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ $row['passRate'] !== null ? $row['passRate'] . '%' : '—' }}</td>
                                        <td class="px-4 py-3">
                                            @foreach($row['top'] as $p)
                                                <div>{{ \App\Services\TerminalReportService::ordinal($p['position']) }} {{ $p['student']->full_name }} <span class="text-xs text-gray-500">({{ $p['average'] }}%)</span></div>
                                            @endforeach
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($row['subjects']->isNotEmpty())
                                                <div class="text-green-700">{{ $row['subjects']->first()['subject']->title }} ({{ $row['subjects']->first()['average'] }})</div>
                                                <div class="text-red-700">{{ $row['subjects']->last()['subject']->title }} ({{ $row['subjects']->last()['average'] }})</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('scores.broadsheet', ['classGroup' => $row['class'], 'semester_id' => $term->id]) }}" class="text-primary-600 hover:underline text-xs">Broadsheet</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($classes->filter(fn ($c) => $c['subjects']->isNotEmpty()) as $row)
                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h4 class="font-semibold text-gray-900 mb-3">{{ $row['class']->name }} — subject averages</h4>
                            @foreach($row['subjects'] as $s)
                                <div class="flex items-center gap-2 text-sm mb-1">
                                    <span class="w-44 truncate text-gray-700" title="{{ $s['subject']->title }}">{{ $s['subject']->title }}</span>
                                    <div class="flex-1 h-3 bg-gray-100 rounded">
                                        <div class="h-3 rounded {{ $s['average'] >= 70 ? 'bg-green-500' : ($s['average'] >= 50 ? 'bg-primary-500' : 'bg-red-400') }}" style="width: {{ min(100, $s['average']) }}%"></div>
                                    </div>
                                    <span class="w-10 text-right text-gray-600">{{ $s['average'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-10 text-center text-gray-400">No classes or scores yet for this term.</div>
            @endif
        </div>
    </div>
</x-app-layout>
