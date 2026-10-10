<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My Profile') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 md:grid-cols-3">
                @csrf
                @method('PUT')

                <div class="flex flex-col items-center rounded-lg bg-white p-6 shadow-sm">
                    <div class="mb-4 h-32 w-32 overflow-hidden rounded-full bg-gray-100">
                        @if($user->profile_photo)
                            <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-4xl font-bold text-gray-300">{{ mb_substr($user->name, 0, 1) }}</div>
                        @endif
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $user->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $user->getRoleNames()->implode(', ') ?: ucfirst($user->role) }}</p>
                    <label class="mt-4 cursor-pointer text-sm font-medium text-primary-600 hover:underline">
                        Change photo <input type="file" name="profile_photo" accept="image/*" class="hidden">
                    </label>
                </div>

                <div class="space-y-6 md:col-span-2">
                    @if($errors->any())
                        <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
                    @endif

                    <div class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Contact details</h3>
                        <x-input name="name" label="Full name" :value="old('name', $user->name)" required />
                        <x-input name="email" type="email" label="Email (used to sign in)" :value="old('email', $user->email)" required />
                        <x-input name="phone" type="tel" label="Mobile number" :value="old('phone', $user->phone)" placeholder="024 XXX XXXX"
                                 helper="If you forget your password, a reset link can be texted to this number." />
                    </div>

                    <div class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Change password <span class="text-sm font-normal text-gray-400">(optional)</span></h3>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-input name="password" type="password" label="New password" autocomplete="new-password" />
                            <x-input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-button type="submit" icon="fas fa-save">Save Profile</x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
