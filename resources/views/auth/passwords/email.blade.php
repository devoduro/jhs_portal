<x-auth-split title="Forgot password?" subtitle="Enter the email or mobile number on your staff account and we'll send you a link to set a new password." page-title="Reset password">
    @if (session('status'))
        <div class="space-y-5">
            <div class="flex items-start gap-3 rounded-md bg-brand-50 p-4 text-sm text-brand-800">
                <i class="fas fa-paper-plane mt-0.5"></i>
                <p>Check your email inbox (and spam folder) or your phone's messages, then tap the link to choose a new password.</p>
            </div>
            <a href="{{ route('password.request') }}" class="block text-center text-sm font-medium text-brand-600 underline underline-offset-2">Didn't get it? Try again</a>
        </div>
    @else
        <form method="POST" action="{{ route('password.email') }}" class="space-y-5" data-auth-form>
            @csrf
            <div>
                <label for="login" class="mb-1.5 block text-sm font-medium text-gray-800">Email address or mobile number</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                       placeholder="you@school.com or 024 XXX XXXX"
                       class="block w-full rounded-md border px-3.5 py-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100 {{ $errors->has('login') ? 'border-red-400' : 'border-gray-300' }}">
                <p class="mt-1.5 text-xs text-gray-500">An email address gets the link by email; a phone number gets it by SMS.</p>
            </div>

            <button type="submit" class="flex w-full items-center justify-center rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-300 disabled:opacity-70">
                Send reset link
            </button>
        </form>
    @endif

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-800"><i class="fas fa-arrow-left mr-1"></i> Back to sign in</a>
        <p class="mt-3 text-xs text-gray-400">No email or phone on your account? Ask the administrator to reset your password under Staff Accounts.</p>
    </x-slot:footer>
</x-auth-split>
