<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Grade Distribution Report') }}
            </h2>
            <a href="{{ route('reports.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Back to Reports
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('reports.grade-distribution') }}" method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="academic_year_id" class="block text-sm font-medium text-gray-700">Academic Year</label>
                                <select id="academic_year_id" name="academic_year_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                    @foreach($academicYears ?? [] as $year)
                                        <option value="{{ $year->id }}" {{ request('academic_year_id') == $year->id ? 'selected' : '' }}>
                                            {{ $year->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="programme_id" class="block text-sm font-medium text-gray-700">Programme (Optional)</label>
                                <select id="programme_id" name="programme_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">All Programmes</option>
                                    @foreach($programmes ?? [] as $prog)
                                        <option value="{{ $prog->id }}" {{ request('programme_id') == $prog->id ? 'selected' : '' }}>
                                            {{ $prog->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700">
                                Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @if(isset($distribution))
                <!-- Report Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Grade Distribution Report</h3>
                        <p class="text-gray-600">
                            Academic Year: <strong>{{ $academicYear->name }}</strong>
                            @if($programme)
                                | Programme: <strong>{{ $programme->name }}</strong>
                            @else
                                | All Programmes
                            @endif
                        </p>
                        <p class="text-gray-600 mt-2">
                            Average Aggregate: <strong>{{ $averageAggregate !== null ? number_format($averageAggregate, 1) : 'N/A' }}</strong>
                        </p>
                        <p class="text-xs text-gray-400 mt-2">
                            Divisions follow WASSCE best-6-subjects aggregate bands: Division 1 (6&ndash;12), Division 2 (13&ndash;24), Division 3 (25&ndash;36), Division 4 (37+). "Ungraded" covers students with fewer than 6 graded subjects for this academic year.
                        </p>
                    </div>
                </div>

                <!-- Chart -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Division Distribution Chart</h3>
                        <div class="w-full h-80">
                            <canvas id="gradeDistributionChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Division Distribution Data</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Division
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Number of Students
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Percentage
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @php
                                        $totalStudents = array_sum($distribution);
                                    @endphp
                                    @foreach($distribution as $division => $count)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $division }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $count }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $totalStudents > 0 ? number_format(($count / $totalStudents) * 100, 2) : 0 }}%
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Chart Script -->
                @push('scripts')
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('gradeDistributionChart').getContext('2d');

                        const gradeChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: {!! json_encode(array_keys($distribution)) !!},
                                datasets: [{
                                    label: 'Number of Students',
                                    data: {!! json_encode(array_values($distribution)) !!},
                                    backgroundColor: [
                                        'rgba(34, 197, 94, 0.6)',
                                        'rgba(59, 130, 246, 0.6)',
                                        'rgba(245, 158, 11, 0.6)',
                                        'rgba(239, 68, 68, 0.6)',
                                        'rgba(148, 163, 184, 0.6)'
                                    ],
                                    borderColor: [
                                        'rgb(34, 197, 94)',
                                        'rgb(59, 130, 246)',
                                        'rgb(245, 158, 11)',
                                        'rgb(239, 68, 68)',
                                        'rgb(148, 163, 184)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Number of Students'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Division'
                                        }
                                    }
                                },
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Division Distribution'
                                    },
                                    legend: {
                                        display: false
                                    }
                                }
                            }
                        });
                    });
                </script>
                @endpush
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <p class="text-gray-600">Please select filters and generate the report.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
