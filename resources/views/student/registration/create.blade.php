@extends('components.student-app-layout')

@section('header')
    <div class="bg-white rounded-2xl p-6 mb-6 shadow-sm border border-gray-100">
        <h2 class="text-2xl font-bold text-gray-800">{{ count($registeredSubjectIds) > 0 ? 'Edit Registration' : 'Register Subjects' }}</h2>
        <p class="text-gray-500 mt-1">{{ $currentSemester->name }} &bull; {{ $currentSemester->academicYear->name ?? '' }}</p>
    </div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Available Subjects</h3>
            <a href="{{ route('student.registration.index') }}" class="text-sm text-primary-600 hover:underline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        @if($availableSubjects->isEmpty())
            <div class="p-8 text-center text-gray-400">
                No subjects have been assigned to your programme for this semester yet.
            </div>
        @else
            <div class="px-6 pt-6">
                <p class="text-sm text-gray-500 mb-3">Check a subject to register it, or uncheck an already-registered subject to drop it, then save.</p>
                <div class="flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-emerald-50 text-emerald-700">
                        <i class="fas fa-book"></i> Subjects Selected: <span id="selected-count">0</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-indigo-50 text-indigo-700">
                        <i class="fas fa-award"></i> Total Credit Hours: <span id="selected-credits">0</span>
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('student.registration.store') }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credit Hours</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($availableSubjects as $subject)
                                @php $isRegistered = in_array($subject->id, $registeredSubjectIds); @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}"
                                            data-credit-hours="{{ $subject->credit_hours }}"
                                            class="subject-checkbox h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                            {{ $isRegistered ? 'checked' : '' }}>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $subject->code }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $subject->title }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $subject->credit_hours }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $subject->is_core ? 'Core' : 'Elective' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-6 flex justify-end gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        <i class="fas fa-check"></i> Save Registration
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function updateRegistrationSummary() {
        const checked = document.querySelectorAll('.subject-checkbox:checked');
        let totalCredits = 0;
        checked.forEach(cb => totalCredits += parseFloat(cb.dataset.creditHours || 0));

        document.getElementById('selected-count').textContent = checked.length;
        document.getElementById('selected-credits').textContent = totalCredits.toFixed(2).replace(/\.?0+$/, '');
    }

    document.querySelectorAll('.subject-checkbox').forEach(cb => cb.addEventListener('change', updateRegistrationSummary));
    updateRegistrationSummary();
</script>
@endpush
@endsection
