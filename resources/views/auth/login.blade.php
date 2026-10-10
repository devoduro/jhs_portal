<x-auth-split title="Welcome back" subtitle="Staff sign in — please enter your details" page-title="Staff sign in">
    <form method="POST" action="{{ route('login') }}" class="space-y-5" data-auth-form>
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-800">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="block w-full rounded-md border px-3.5 py-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100 {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}">
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-800">Password</label>
            <div class="relative">
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="block w-full rounded-md border border-gray-300 px-3.5 py-3 pr-11 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <button type="button" data-toggle-password="password" aria-label="Show password"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-400 hover:text-gray-600"><i class="fas fa-eye"></i></button>
            </div>
        </div>

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-gray-700">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="font-medium text-brand-600 underline underline-offset-2 hover:text-brand-800">Forgot password</a>
        </div>

        <button type="submit" class="flex w-full items-center justify-center rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-300 disabled:opacity-70">
            Sign in
        </button>

        <a href="{{ route('student.login') }}" class="flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            <i class="fas fa-child-reaching text-brand-600"></i> Pupil / parent sign in
        </a>
    </form>

    <x-slot:footer>
        No account yet? Ask the school administrator to create one for you.
    </x-slot:footer>
</x-auth-split>
