@extends('components.student-app-layout')

@section('title', 'My Semester Reports')
@section('subtitle', 'View and print your report card for any semester you have results in')

@push('styles')
<style>
    @media print {
        body * { visibility: hidden; }
        .report-card-printable, .report-card-printable * { visibility: visible; }
        .report-card-printable { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>
@endpush

@section('content')
<div class="py-6">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Semester Reports for {{ $student->full_name }}</h3>
                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-primary-50 text-primary-700">{{ $availableReports->count() }} {{ Str::plural('record', $availableReports->count()) }}</span>
                </div>

                @if($availableReports->isEmpty())
                    <p class="text-center text-gray-500 py-8">No results have been recorded for you yet, so there's nothing to generate a report for.</p>
                @else
                    <form method="GET" action="{{ route('student.report-card') }}" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Available Reports</label>
                            <select name="semester_id" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 text-sm">
                                <option value="">Select a Semester to View Report</option>
                                @foreach($availableReports as $report)
                                    <option value="{{ $report['semester_id'] }}" {{ (int) $selectedSemesterId === $report['semester_id'] ? 'selected' : '' }}>
                                        {{ $report['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="bg-primary-600 text-white rounded-md px-6 py-2 text-sm font-medium hover:bg-primary-700 whitespace-nowrap">
                            Generate Selected Report
                        </button>
                    </form>

                    <div class="flex items-center gap-3 my-4">
                        <hr class="flex-1 border-gray-200">
                        <span class="text-xs text-gray-400 uppercase">Or</span>
                        <hr class="flex-1 border-gray-200">
                    </div>

                    <div class="text-center">
                        
                    </div>
                @endif
            </div>
        </div>

        @if($reportData)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="no-print p-4 bg-gray-50 border-b border-gray-200 flex justify-end">
                    <button onclick="window.print()" class="bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700">
                        <i class="fas fa-print mr-1"></i> Print / Save as PDF
                    </button>
                </div>
                <div class="report-card-printable p-8">
                    @include('report-card._card', $reportData)
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
