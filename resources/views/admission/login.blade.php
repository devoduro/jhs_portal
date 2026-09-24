<x-admission-layout title="Applicant Login">
    <div class="bg-white shadow-sm rounded-lg p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-1">Applicant Login</h1>
        <p class="text-sm text-gray-500 mb-6">Log in with your BECE index number and the password you set after paying the admission fee.</p>

        <form method="POST" action="{{ route('admission.login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">BECE Index Number</label>
                <input type="text" name="index_number" value="{{ old('index_number') }}" required
                       class="block w-full pl-3 pr-3 py-2.5 border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required
                       class="block w-full pl-3 pr-3 py-2.5 border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white rounded-md px-4 py-2.5 text-sm font-medium hover:bg-blue-700">
                Log In
            </button>
        </form>

        <p class="text-sm text-gray-500 mt-6 text-center">
            Haven't paid yet? <a href="{{ route('admission.verify') }}" class="text-blue-600 hover:underline">Start here</a>
        </p>
    </div>
</x-admission-layout>
