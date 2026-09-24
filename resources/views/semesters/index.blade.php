<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Terms') }}</h2>
            <div class="flex gap-2">
                <x-button href="{{ route('settings.academic-years') }}" variant="secondary" icon="fas fa-calendar">Academic Years</x-button>
                <x-button href="{{ route('semesters.create') }}" icon="fas fa-plus">Add Term</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                @if($semesters->isEmpty())
                    <div class="text-center py-10">
                        <p class="text-gray-500">No terms yet. Create an academic year first, then its First, Second and Third Terms.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Term</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reopens / Vacates</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Next term begins</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">School days</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($semesters as $semester)
                                    <tr class="{{ $semester->is_current ? 'bg-green-50' : '' }}">
                                        <td class="px-6 py-3">
                                            <div class="font-medium text-gray-900">{{ $semester->name }}
                                                @if($semester->is_current)<span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-600 text-white">Current</span>@endif
                                            </div>
                                            <div class="text-xs text-gray-500">{{ $semester->academicYear->name ?? '' }}</div>
                                        </td>
                                        <td class="px-6 py-3 text-gray-600 whitespace-nowrap">
                                            {{ $semester->start_date?->format('d M Y') }} – {{ ($semester->vacation_date ?? $semester->end_date)?->format('d M Y') }}
                                        </td>
                                        <td class="px-6 py-3 text-gray-600">{{ $semester->next_term_begins?->format('d M Y') ?? '—' }}</td>
                                        <td class="px-6 py-3 text-gray-600">{{ $semester->attendance_days ?? '—' }}</td>
                                        <td class="px-6 py-3 text-right whitespace-nowrap space-x-3">
                                            @unless($semester->is_current)
                                                <form action="{{ route('semesters.set-current', ['semester' => $semester->id]) }}" method="POST" class="inline">
                                                    @csrf @method('PUT')
                                                    <button type="submit" class="text-green-700 hover:underline text-xs" onclick="return confirm('Make {{ $semester->name }} ({{ $semester->academicYear->name ?? '' }}) the current term?')">Make current</button>
                                                </form>
                                            @endunless
                                            @can('send-sms')
                                                <form action="{{ route('semesters.notify-parents', $semester) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-blue-600 hover:underline text-xs" onclick="return confirm('Text every parent a link to their child\'s {{ $semester->name }} terminal report?')">Text reports to parents</button>
                                                </form>
                                            @endcan
                                            <a href="{{ route('semesters.edit', $semester) }}" class="text-indigo-600 hover:text-indigo-900" title="Edit"><i class="fas fa-edit"></i></a>
                                            <form action="{{ route('semesters.destroy', $semester) }}" method="POST" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Delete this term?')"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3">{{ $semesters->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
