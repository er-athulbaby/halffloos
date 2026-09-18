@props(['title' => null])

@php
    $user = auth()->user();
    $isMerchant = $user?->role === \App\Enums\UserRole::Merchant;

    $tabs = match ($user?->role) {
        \App\Enums\UserRole::Merchant => [
            ['route' => 'merchant.stock', 'icon' => 'list-plus', 'label' => __('List stock')],
            ['route' => 'merchant.till', 'icon' => 'barcode', 'label' => __('Collect')],
        ],
        \App\Enums\UserRole::Admin => [
            ['route' => 'admin.shops', 'icon' => 'storefront', 'label' => __('Shops')],
            ['route' => 'browse', 'icon' => 'shopping-bag', 'label' => __('Browse')],
        ],
        \App\Enums\UserRole::Customer => [
            ['route' => 'browse', 'icon' => 'storefront', 'label' => __('Browse')],
            ['route' => 'reservations', 'icon' => 'ticket', 'label' => __('My codes')],
        ],
        default => [],
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <title>{{ $title ?? __('Halffloos') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh bg-background text-foreground font-sans antialiased">
    @auth
        {{-- Quiet header: the brand is small, the screen title carries the page. --}}
        <header class="sticky top-0 z-10 border-b border-border-subtle bg-card/95 backdrop-blur">
            <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-3">
                <span class="text-base font-bold tracking-tight text-primary">{{ __('Halffloos') }}</span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-sm text-muted-foreground
                                   focus:outline-none focus:ring-2 focus:ring-primary">
                        <x-icon name="sign-out" class="size-4" />
                        <span class="sr-only sm:not-sr-only">{{ __('Sign out') }}</span>
                    </button>
                </form>
            </div>
        </header>
    @endauth

    <main class="mx-auto max-w-2xl px-4 py-5 {{ $tabs ? 'pb-28' : 'pb-10' }}">
        {{ $slot }}
    </main>

    @if ($tabs)
        {{-- Bottom navigation: thumb-reachable, two items, never more than five. --}}
        <nav class="fixed inset-x-0 bottom-0 z-10 border-t border-border-subtle bg-card"
             style="padding-bottom: env(safe-area-inset-bottom)">
            <div class="mx-auto flex max-w-2xl">
                @foreach ($tabs as $tab)
                    @php($active = request()->routeIs($tab['route']))
                    <a href="{{ route($tab['route']) }}"
                       @if ($active) aria-current="page" @endif
                       @class([
                           'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-xs font-medium',
                           'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                           'text-primary' => $active,
                           'text-muted-foreground' => ! $active,
                       ])>
                        <x-icon :name="$tab['icon']" class="size-6" />
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>
    @endif

    @livewireScripts
</body>
</html>
