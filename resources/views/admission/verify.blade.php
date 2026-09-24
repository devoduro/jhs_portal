<x-admission-layout title="Check Your Placement">
    <div class="bg-white shadow-sm rounded-lg p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-2">Online Admission</h1>
        <p class="text-sm text-gray-500 mb-6">Enter the BECE index number you were placed under to begin. If you've already paid the admission fee, this will take you straight to the login page.</p>

        <form method="POST" action="{{ route('admission.verify.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">BECE Index Number</label>
                <input type="text" name="index_number" value="{{ old('index_number') }}" required
                       placeholder="e.g. 1234567890"
                       class="block w-full pl-3 pr-3 py-2.5 border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white rounded-md px-4 py-2.5 text-sm font-medium hover:bg-blue-700">
                Continue
            </button>
        </form>

        <p class="text-sm text-gray-500 mt-6 text-center">
            Already have a password? <a href="{{ route('admission.login') }}" class="text-blue-600 hover:underline">Log in here</a>
        </p>
    </div>
</x-admission-layout>
