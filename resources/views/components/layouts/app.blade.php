@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#059669">
    <title>{{ $title ?? __('Halffloos') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh bg-background text-foreground font-sans antialiased">
    @auth
        @php($isMerchant = auth()->user()->role === \App\Enums\UserRole::Merchant)
        <header class="bg-primary text-on-primary">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
                <a href="{{ route($isMerchant ? 'merchant.stock' : 'browse') }}"
                   class="text-lg font-bold tracking-tight">
                    {{ __('Halffloos') }}
                </a>
                <div class="flex items-center gap-3 text-sm">
                    @if ($isMerchant)
                        <nav class="flex items-center gap-3">
                            <a href="{{ route('merchant.stock') }}"
                               @class(['underline underline-offset-4' => request()->routeIs('merchant.stock')])>
                                {{ __('List stock') }}
                            </a>
                            <a href="{{ route('merchant.till') }}"
                               @class(['underline underline-offset-4' => request()->routeIs('merchant.till')])>
                                {{ __('Collections') }}
                            </a>
                        </nav>
                    @else
                        <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="button" onclick="this.form.submit()"
                                class="min-h-11 rounded-lg px-3 font-medium underline underline-offset-4">
                            {{ __('Sign out') }}
                        </button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main class="mx-auto max-w-3xl px-4 py-6">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
