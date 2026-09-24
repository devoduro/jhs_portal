<x-admission-layout title="Pay Admission Fee">
    <div class="bg-white shadow-sm rounded-lg p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-1">Pay Admission Fee</h1>
        <p class="text-sm text-gray-500 mb-6">Congratulations, {{ $application->full_name }}! Pay the admission fee below via Mobile Money to continue your registration.</p>

        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6 flex items-center justify-between">
            <span class="text-sm text-blue-800">Admission Fee</span>
            <span class="text-2xl font-bold text-blue-900">GHS {{ number_format($feeAmount, 2) }}</span>
        </div>

        <form method="POST" action="{{ route('admission.pay.submit', $application->index_number) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Money Number</label>
                <input type="text" name="mobile" value="{{ old('mobile') }}" required placeholder="e.g. 0244000000"
                       class="block w-full pl-3 pr-3 py-2.5 border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Network</label>
                <select name="network" required class="block w-full pl-3 pr-10 py-2.5 border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    <option value="">Select network&hellip;</option>
                    <option value="MTN" {{ old('network') === 'MTN' ? 'selected' : '' }}>MTN Mobile Money</option>
                    <option value="VOD" {{ old('network') === 'VOD' ? 'selected' : '' }}>Telecel Cash</option>
                    <option value="ATL" {{ old('network') === 'ATL' ? 'selected' : '' }}>AirtelTigo Money</option>
                </select>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white rounded-md px-4 py-2.5 text-sm font-medium hover:bg-blue-700">
                Pay GHS {{ number_format($feeAmount, 2) }}
            </button>
        </form>
    </div>
</x-admission-layout>
