<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Results') }} &mdash; {{ $quiz->title }}</h2>
            <x-button href="{{ route('quizzes.show', $quiz) }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Back to Quiz') }}</x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if($attempts->isEmpty())
                    <div class="text-center py-12">
                        <i class="fas fa-chart-bar text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-500">No attempts yet</h3>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($attempts as $attempt)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $attempt->student->full_name ?? 'Unknown' }}
                                            <div class="text-xs text-gray-500">{{ $attempt->student->index_number ?? '' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $attempt->score ?? '-' }} / {{ $attempt->total_marks ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $attempt->percentage !== null ? $attempt->percentage.'%' : '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($attempt->is_passed === null)
                                                <span class="text-gray-400">-</span>
                                            @elseif($attempt->is_passed)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Pass</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Fail</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $attempt->submitted_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
