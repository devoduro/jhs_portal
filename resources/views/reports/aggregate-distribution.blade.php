<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Aggregate Distribution Report') }}
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
                    <form action="{{ route('reports.aggregate-distribution') }}" method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

            @if(isset($divisions))
                <!-- Report Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aggregate Distribution Report</h3>
                        <p class="text-gray-600">
                            @if($programme)
                                Programme: <strong>{{ $programme->name }}</strong>
                            @else
                                All Programmes
                            @endif
                        </p>
                    </div>
                </div>

                <!-- Division Distribution Chart -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Division Distribution</h3>
                                <div class="h-80">
                                    <canvas id="divisionChart"></canvas>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Division Breakdown</h3>
                                <div class="h-80">
                                    <canvas id="divisionPieChart"></canvas>
                                </div>
                            </div>
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
                                        $totalStudents = array_sum($divisions);
                                    @endphp
                                    @foreach($divisions as $division => $count)
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
                                    <tr class="bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            Total
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $totalStudents }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            100%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Division Explanation -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Division Explanation</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-medium text-gray-800 mb-2">Division Criteria</h4>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Division
                                            </th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Aggregate Range
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">Division 1</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">6 - 12</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">Division 2</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">13 - 24</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">Division 3</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">25 - 36</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">Division 4</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">37+</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">Ungraded</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">Fewer than 6 graded subjects</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-800 mb-2">Notes</h4>
                                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-600">
                                    <li>Division is based on the WASSCE aggregate, the sum of grade points from a student's best 6 subjects (lower is better).</li>
                                    <li>Students need at least 6 graded subjects for an aggregate and division to be calculated.</li>
                                    <li>Students with incomplete records or pending results may show as "Ungraded" in this report.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart Script -->
                @push('scripts')
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Prepare data for charts
                        const divisionLabels = {!! json_encode(array_keys($divisions)) !!};
                        const divisionCounts = {!! json_encode(array_values($divisions)) !!};

                        // Bar Chart
                        const barCtx = document.getElementById('divisionChart').getContext('2d');
                        const barChart = new Chart(barCtx, {
                            type: 'bar',
                            data: {
                                labels: divisionLabels,
                                datasets: [{
                                    label: 'Number of Students',
                                    data: divisionCounts,
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
                                    }
                                },
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Division Distribution'
                                    }
                                }
                            }
                        });

                        // Pie Chart
                        const pieCtx = document.getElementById('divisionPieChart').getContext('2d');
                        const pieChart = new Chart(pieCtx, {
                            type: 'pie',
                            data: {
                                labels: divisionLabels,
                                datasets: [{
                                    data: divisionCounts,
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
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Division Breakdown'
                                    },
                                    legend: {
                                        position: 'right'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.label || '';
                                                const value = context.raw || 0;
                                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                                return `${label}: ${value} (${percentage}%)`;
                                            }
                                        }
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
