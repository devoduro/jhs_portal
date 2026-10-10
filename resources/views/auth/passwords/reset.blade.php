<x-auth-split title="Set a new password" subtitle="Choose a password you haven't used here before — at least 8 characters." page-title="New password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5" data-auth-form>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-800">Email address</label>
            <input id="email" name="email" type="email" value="{{ $email ?? old('email') }}" required autocomplete="username" {{ ($email ?? null) ? 'readonly' : 'autofocus' }}
                   class="block w-full rounded-md border border-gray-300 px-3.5 py-3 text-sm shadow-sm read-only:bg-gray-50 read-only:text-gray-500 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100">
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-800">New password</label>
            <div class="relative">
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" {{ ($email ?? null) ? 'autofocus' : '' }}
                       class="block w-full rounded-md border border-gray-300 px-3.5 py-3 pr-11 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <button type="button" data-toggle-password="password" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-400 hover:text-gray-600"><i class="fas fa-eye"></i></button>
            </div>
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-800">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                   class="block w-full rounded-md border border-gray-300 px-3.5 py-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100">
        </div>

        <button type="submit" class="flex w-full items-center justify-center rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-300 disabled:opacity-70">
            Save new password
        </button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-800"><i class="fas fa-arrow-left mr-1"></i> Back to sign in</a>
    </x-slot:footer>
</x-auth-split>
