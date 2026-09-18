<x-layouts.app :title="__('Sign in')">
    <div class="mx-auto max-w-sm">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('Halffloos') }}</h1>
        <p class="mt-1 text-muted-foreground">{{ __('Sign in to list your stock.') }}</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username"
                       value="{{ old('email') }}"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle bg-card px-3
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('email')
                    <p class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle bg-card px-3
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('password')
                    <p class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1"
                       class="size-5 rounded border-border-subtle text-primary focus:ring-primary">
                {{ __('Keep me signed in on this device') }}
            </label>

            <button type="submit"
                    class="min-h-11 w-full rounded-lg bg-primary px-4 font-bold text-on-primary
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                {{ __('Sign in') }}
            </button>
        </form>

        <p class="mt-6 text-sm">
            <a href="{{ route('register') }}" class="font-medium text-primary underline underline-offset-4">
                {{ __('New here? Create an account') }}
            </a>
        </p>

        <p class="mt-4 text-sm text-muted-foreground">
            {{ __('Running a shop? Shops are added by Halffloos after we check your CR and food licence. Contact us to get set up.') }}
        </p>
    </div>
</x-layouts.app>
