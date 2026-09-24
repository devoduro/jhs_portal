<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Online Admissions') }}
            </h2>
            <div class="flex gap-2">
                <x-button href="{{ route('admissions.import.form') }}" variant="secondary" icon="fas fa-upload">
                    {{ __('Import Placement List') }}
                </x-button>
                <a href="{{ route('admission.verify') }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fas fa-external-link-alt"></i> View Applicant Portal
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-md px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-md px-4 py-3 text-sm">{{ session('warning') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-md px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                @foreach(\App\Models\AdmissionApplication::STATUS_LABELS as $statusKey => $label)
                    <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                        <div class="text-2xl font-semibold text-gray-900">{{ $statusCounts[$statusKey] ?? 0 }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('admissions.settings') }}" class="flex flex-wrap items-end gap-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Admission Fee (GHS)</label>
                            <input type="number" step="0.01" min="0" name="admission_fee_amount" value="{{ $feeAmount }}" class="block w-40 pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md">
                        </div>
                        <x-button type="submit" size="sm">{{ __('Save') }}</x-button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="GET" action="{{ route('admissions.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or index number" class="block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <select name="status" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md">
                                <option value="">All Statuses</option>
                                @foreach(\App\Models\AdmissionApplication::STATUS_LABELS as $statusKey => $label)
                                    <option value="{{ $statusKey }}" {{ request('status') === $statusKey ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <select name="programme_id" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md">
                                <option value="">All Programmes</option>
                                @foreach($programmes as $programme)
                                    <option value="{{ $programme->id }}" {{ (int) request('programme_id') === $programme->id ? 'selected' : '' }}>{{ $programme->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            @if(request()->hasAny(['search', 'status', 'programme_id']))
                                <a href="{{ route('admissions.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary-600">Clear</a>
                            @endif
                        </div>
                    </form>
                </div>

                @if($applications->isEmpty())
                    <div class="text-center py-12">
                        <i class="fas fa-user-graduate text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-500">No applications found</h3>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Index Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Programme</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @php
                                    $statusColors = [
                                        'placed' => 'bg-gray-100 text-gray-800',
                                        'payment_pending' => 'bg-orange-100 text-orange-800',
                                        'paid' => 'bg-yellow-100 text-yellow-800',
                                        'submitted' => 'bg-blue-100 text-blue-800',
                                        'admitted' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                @foreach($applications as $application)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $application->full_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $application->index_number }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $application->programme->name ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$application->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ \App\Models\AdmissionApplication::STATUS_LABELS[$application->status] ?? $application->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('admissions.show', $application) }}" class="text-primary-600 hover:text-primary-900">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">
                        {{ $applications->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
