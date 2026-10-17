<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Change Password') }}</h2>
    </x-slot>

    @php $field = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-[#42a5dc] focus:ring-[#42a5dc]/30 pr-10'; @endphp

    <div class="py-10">
        <div class="mx-auto max-w-lg sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-[#42a5dc]/10 to-transparent px-6 py-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#42a5dc] text-white"><i class="fas fa-lock"></i></span>
                    <div>
                        <h3 class="font-semibold text-gray-900">Choose a new password</h3>
                        <p class="text-xs text-gray-500">Signed in as {{ auth()->user()->email }}</p>
                    </div>
                </div>

                <div class="p-6">
                    @if($is_first_login)
                        <div class="mb-5 rounded-lg border-l-4 border-yellow-500 bg-yellow-50 p-4 text-sm text-yellow-800" role="alert">
                            <p class="font-bold">First time signing in</p>
                            <p>For security, you must change your temporary password before continuing.</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5"
                          x-data="{ show: false, pw: '', confirm: '',
                                    get rules() { return [
                                        ['At least 8 characters (letters, numbers or both)', this.pw.length >= 8],
                                    ]; },
                                    get strong() { return this.rules.every(r => r[1]); },
                                    get matches() { return this.confirm !== '' && this.pw === this.confirm; } }">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700">Current password</label>
                            <input id="current_password" type="password" name="current_password" required autofocus autocomplete="current-password" :type="show ? 'text' : 'password'" class="{{ $field }}">
                            @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">New password</label>
                            <input id="password" type="password" name="password" required autocomplete="new-password" x-model="pw" :type="show ? 'text' : 'password'" class="{{ $field }}">
                            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            <ul class="mt-2 space-y-1 text-sm">
                                <template x-for="r in rules" :key="r[0]">
                                    <li :class="r[1] ? 'text-green-600' : 'text-gray-500'"><i class="fas mr-1.5 w-4" :class="r[1] ? 'fa-circle-check' : 'fa-circle'"></i><span x-text="r[0]"></span></li>
                                </template>
                            </ul>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm new password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" x-model="confirm" :type="show ? 'text' : 'password'" class="{{ $field }}">
                            <p class="mt-1 text-sm" x-show="confirm !== ''" x-cloak :class="matches ? 'text-green-600' : 'text-red-600'" x-text="matches ? 'Passwords match' : 'Passwords do not match yet'"></p>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" x-model="show" class="rounded border-gray-300"> Show passwords</label>

                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                            @unless($is_first_login)
                                <x-button href="{{ route('dashboard') }}" variant="secondary">Cancel</x-button>
                            @endunless
                            <x-button type="submit" x-bind:disabled="!(strong && matches)" x-bind:class="!(strong && matches) ? 'opacity-50 cursor-not-allowed' : ''" icon="fas fa-key">Update Password</x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
