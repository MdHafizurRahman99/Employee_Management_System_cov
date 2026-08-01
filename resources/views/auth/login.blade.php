<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-4 text-sm text-gray-600">
        Sign in with your employee email and PIN. Administrators may continue using their existing password.
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required
                autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- PIN / Password -->
        <div class="mt-4">
            <x-input-label for="pin_code" :value="__('PIN or Password')" />

            <x-text-input id="pin_code" class="block mt-1 w-full" type="password" name="pin_code" required
                autocomplete="current-password" />

            <x-input-error :messages="$errors->get('pin_code')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4 px-2">
            {{-- <x-primary-button class="ms-3">
                <a class=" text-sm  hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    href="{{ route('register') }}">
                    {{ __('Register') }}
                </a>
            </x-primary-button> --}}
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif


            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>

        </div>
        <div class="flex items-center justify-end mt-4 px-2">
            <p class="text-sm text-gray-500">
                If you need access, please contact your administrator.
            </p>
        </div>
    </form>
</x-guest-layout>
