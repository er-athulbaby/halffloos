<?php

use App\Actions\CancelReservation;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Reservation;
use Livewire\Component;

new class extends Component
{
    public ?string $failed = null;
    public ?string $cancelled = null;

    public function cancel(int $reservationId): void
    {
        $this->failed = null;
        $this->cancelled = null;

        $reservation = Reservation::findOrFail($reservationId);

        try {
            (new CancelReservation)->handle($reservation, auth()->user());
        } catch (ReservationFailed $e) {
            $this->failed = $e->getMessage();

            return;
        }

        $this->cancelled = __('Cancelled. The shop can sell it to someone else.');
    }

    public function with(): array
    {
        $mine = auth()->user()->reservations()
            ->with('offer.store')
            ->orderByDesc('id')
            ->get();

        return [
            // Live codes first — this screen exists to be held up at a counter.
            'live' => $mine->where('status', ReservationStatus::Reserved)
                ->filter(fn ($r) => $r->offer->pickup_end->isFuture()),
            'past' => $mine->reject(
                fn ($r) => $r->status === ReservationStatus::Reserved && $r->offer->pickup_end->isFuture()
            ),
        ];
    }
};
?>

<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">{{ __('My codes') }}</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">{{ __('Show a code at the shop and pay there.') }}</p>
    </div>

    @if ($cancelled)
        <div class="flex items-start gap-2 rounded-xl border border-border-subtle bg-card p-3 text-sm" role="status">
            <x-icon name="check-circle" class="size-5 text-primary" />
            <span>{{ $cancelled }}</span>
        </div>
    @endif

    @if ($failed)
        <div class="flex items-start gap-2 rounded-xl border border-destructive bg-card p-3 text-sm" role="alert">
            <x-icon name="warning-circle" class="size-5 text-destructive" />
            <span>{{ $failed }}</span>
        </div>
    @endif

    @forelse ($live as $r)
        <article class="overflow-hidden rounded-2xl border-2 border-primary bg-card">
            <div class="bg-primary px-4 py-3 text-on-primary">
                <p class="text-xs font-semibold uppercase tracking-wide opacity-80">{{ __('Pickup code') }}</p>
                <p class="font-mono text-4xl font-bold tracking-[0.2em] tabular-nums">{{ $r->pickup_code }}</p>
            </div>

            <div class="flex gap-3 p-4">
                <x-offer-image :offer="$r->offer" size="size-16" />

                <div class="min-w-0 flex-1">
                    <h2 class="truncate font-semibold leading-tight">
                        {{ $r->qty > 1 ? $r->qty.' × ' : '' }}{{ $r->offer->title }}
                    </h2>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ $r->offer->store->name }} · {{ $r->offer->store->area }}
                    </p>
                    <p class="mt-1 flex items-center gap-1.5 text-sm">
                        <x-icon name="clock" class="size-4 text-accent" />
                        <span class="font-medium text-accent">
                            {{ __('Collect by :time', ['time' => $r->offer->pickup_end->format('H:i')]) }}
                        </span>
                    </p>
                    <p class="mt-1 font-bold tabular-nums text-primary">
                        {{ \App\Support\Money::fromFils($r->offer->price_fils->fils() * $r->qty)->format() }}
                        <span class="font-normal text-muted-foreground">{{ __('to pay at the shop') }}</span>
                    </p>
                </div>
            </div>

            @if ($r->offer->store->pickup_instructions)
                <p class="border-t border-border-subtle px-4 py-2 text-sm text-muted-foreground">
                    {{ $r->offer->store->pickup_instructions }}
                </p>
            @endif

            <div class="border-t border-border-subtle px-4 py-2">
                <button type="button" wire:click="cancel({{ $r->id }})"
                        wire:confirm="{{ __('Cancel this reservation? The shop can then sell it to someone else.') }}"
                        class="min-h-11 text-sm font-medium text-muted-foreground underline underline-offset-4
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    {{ __('Cancel reservation') }}
                </button>
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-border-subtle bg-card p-8 text-center">
            <x-icon name="ticket" class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-3 font-medium">{{ __('No codes yet') }}</p>
            <p class="mt-1 text-sm text-muted-foreground">{{ __('Reserve something and the code appears here.') }}</p>
            <a href="{{ route('browse') }}"
               class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-primary px-5 font-bold text-on-primary
                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                {{ __('Browse food') }}
            </a>
        </div>
    @endforelse

    @if ($past->isNotEmpty())
        <section>
            <h2 class="text-sm font-bold uppercase tracking-wide text-muted-foreground">{{ __('Earlier') }}</h2>

            @foreach ($past as $r)
                <div class="mt-2 flex items-center gap-3 rounded-xl border border-border-subtle bg-card p-3">
                    <x-offer-image :offer="$r->offer" size="size-12" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $r->offer->title }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ $r->offer->store->name }}</p>
                    </div>
                    <span @class([
                        'shrink-0 rounded-lg px-2 py-1 text-xs font-medium',
                        'bg-muted text-muted-foreground' => $r->status !== \App\Enums\ReservationStatus::Collected,
                        'bg-primary text-on-primary' => $r->status === \App\Enums\ReservationStatus::Collected,
                    ])>
                        {{ match ($r->status) {
                            \App\Enums\ReservationStatus::Collected => __('Collected'),
                            \App\Enums\ReservationStatus::Cancelled => __('Cancelled'),
                            \App\Enums\ReservationStatus::NoShow => __('Missed'),
                            default => __('Expired'),
                        } }}
                    </span>
                </div>
            @endforeach
        </section>
    @endif
</div>
