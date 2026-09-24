<x-admission-layout title="Set Your Password">
    <div class="bg-white shadow-sm rounded-lg p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-1">Payment Confirmed</h1>
        <p class="text-sm text-gray-500 mb-6">Set a password to log in and complete the rest of your application, {{ $application->full_name }}.</p>

        <form method="POST" action="{{ route('admission.set-password.submit', $application->index_number) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required minlength="6"
                       class="block w-full pl-3 pr-3 py-2.5 border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" name="password_confirmation" required minlength="6"
                       class="block w-full pl-3 pr-3 py-2.5 border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white rounded-md px-4 py-2.5 text-sm font-medium hover:bg-blue-700">
                Set Password &amp; Continue
            </button>
        </form>
    </div>
</x-admission-layout>
