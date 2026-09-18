<x-layouts.app :title="__('Create an account')">
    <div class="mx-auto max-w-sm">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('Halffloos') }}</h1>
        <p class="mt-1 text-muted-foreground">{{ __('Cheap food from shops near you.') }}</p>

        <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium">{{ __('Your name') }}</label>
                <input id="name" name="name" type="text" required autofocus autocomplete="name"
                       value="{{ old('name') }}"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle bg-card px-3
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('name') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" required autocomplete="username"
                       value="{{ old('email') }}"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle bg-card px-3
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('email') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium">
                    {{ __('Phone') }} <span class="text-muted-foreground">{{ __('(optional)') }}</span>
                </label>
                <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel"
                       value="{{ old('phone') }}"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle bg-card px-3 tabular-nums
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('phone') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle bg-card px-3
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('password') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       autocomplete="new-password"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle bg-card px-3
                              focus:border-primary focus:ring-2 focus:ring-primary">
            </div>

            <button type="submit"
                    class="min-h-11 w-full rounded-lg bg-primary px-4 font-bold text-on-primary
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                {{ __('Create account') }}
            </button>
        </form>

        <p class="mt-6 text-sm">
            <a href="{{ route('login') }}" class="font-medium text-primary underline underline-offset-4">
                {{ __('Already have an account? Sign in') }}
            </a>
        </p>
    </div>
</x-layouts.app>
