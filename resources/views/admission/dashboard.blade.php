<x-admission-layout title="My Application">
    <div class="flex justify-end mb-4">
        <form method="POST" action="{{ route('admission.logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-500 hover:text-red-600">Log Out</button>
        </form>
    </div>

    <div class="bg-white shadow-sm rounded-lg p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-1">Welcome, {{ $application->full_name }}</h1>
        <p class="text-sm text-gray-500 mb-6">Index Number: {{ $application->index_number }} &bull; Programme: {{ $application->programme->name ?? 'Not yet assigned' }}</p>

        <div class="mb-6">
            @php
                $statusColors = [
                    'paid' => 'bg-yellow-100 text-yellow-800',
                    'submitted' => 'bg-blue-100 text-blue-800',
                    'admitted' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                ];
            @endphp
            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$application->status] ?? 'bg-gray-100 text-gray-800' }}">
                {{ \App\Models\AdmissionApplication::STATUS_LABELS[$application->status] ?? $application->status }}
            </span>
        </div>

        @if($application->status === 'paid')
            <div class="border border-gray-200 rounded-md p-4">
                <p class="text-sm text-gray-700 mb-3">Your payment is confirmed. Complete your profile below to finish your application.</p>
                <a href="{{ route('admission.profile.edit') }}" class="inline-block bg-blue-600 text-white rounded-md px-4 py-2 text-sm hover:bg-blue-700">
                    Complete My Profile
                </a>
            </div>
        @elseif($application->status === 'submitted')
            <div class="border border-gray-200 rounded-md p-4 space-y-3">
                <p class="text-sm text-gray-700">Your application has been submitted and is awaiting review by the school. You'll be notified once a decision is made.</p>
                <div class="flex gap-2">
                    <a href="{{ route('admission.profile.edit') }}" class="inline-block bg-gray-100 text-gray-700 rounded-md px-4 py-2 text-sm hover:bg-gray-200">
                        Edit My Application
                    </a>
                    <a href="{{ route('admission.print-slip') }}" target="_blank" class="inline-block bg-blue-600 text-white rounded-md px-4 py-2 text-sm hover:bg-blue-700">
                        Print Provisional Slip
                    </a>
                </div>
            </div>
        @elseif($application->status === 'admitted')
            <div class="border border-green-200 bg-green-50 rounded-md p-4">
                <p class="text-sm text-green-800 mb-3">Congratulations - you have been admitted! Your school index number and login details have been sent to your contact number.</p>
                <a href="{{ route('admission.print-slip') }}" target="_blank" class="inline-block bg-green-600 text-white rounded-md px-4 py-2 text-sm hover:bg-green-700">
                    Print Admission Slip
                </a>
            </div>
        @elseif($application->status === 'rejected')
            <div class="border border-red-200 bg-red-50 rounded-md p-4">
                <p class="text-sm text-red-800">Your application was not successful this time.</p>
                @if($application->review_notes)
                    <p class="text-sm text-red-700 mt-2">{{ $application->review_notes }}</p>
                @endif
            </div>
        @endif
    </div>
</x-admission-layout>
