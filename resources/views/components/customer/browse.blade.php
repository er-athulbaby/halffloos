<?php

use App\Actions\ReserveOffer;
use App\Enums\OfferStatus;
use App\Enums\StoreStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Offer;
use Livewire\Component;

new class extends Component
{
    public ?string $failed = null;
    public ?string $reservedCode = null;

    public function reserve(int $offerId, int $qty = 1): void
    {
        $this->failed = null;
        $this->reservedCode = null;

        $offer = Offer::findOrFail($offerId);

        try {
            $reservation = (new ReserveOffer)->handle($offer, auth()->user(), $qty);
        } catch (ReservationFailed $e) {
            $this->failed = $e->getMessage();

            return;
        }

        $this->reservedCode = $reservation->pickup_code;
    }

    public function with(): array
    {
        return [
            'offers' => Offer::query()
                ->where('status', OfferStatus::Active)
                ->where('pickup_end', '>', now())
                ->whereRelation('store', 'status', StoreStatus::Approved)
                ->with('store')
                ->orderBy('pickup_end')
                ->get(),
        ];
    }
};
?>

<div class="space-y-4">
    <div>
        <h1 class="text-xl font-bold tracking-tight">{{ __('Save on food near you') }}</h1>
        <p class="text-sm text-muted-foreground">{{ __('Reserve now, pay at the shop when you collect.') }}</p>
    </div>

    @if ($reservedCode)
        <div class="rounded-xl bg-primary p-4 text-on-primary" role="status">
            <p class="font-medium">{{ __('Reserved. Show this code at the shop.') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-widest tabular-nums">{{ $reservedCode }}</p>
        </div>
    @endif

    @if ($failed)
        <div class="rounded-lg bg-destructive px-4 py-3 font-medium text-white" role="alert">
            {{ $failed }}
        </div>
    @endif

    @forelse ($offers as $offer)
        @php($percent = $offer->price_fils->percentOffFrom($offer->retail_value_fils))
        <article class="rounded-xl bg-card p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-bold">{{ $offer->title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ $offer->store->name }} · {{ $offer->store->area }}
                    </p>
                </div>
                <span class="shrink-0 rounded-lg bg-primary px-2 py-1 text-sm font-bold text-on-primary tabular-nums">
                    {{ __(':percent% off', ['percent' => $percent]) }}
                </span>
            </div>

            <p class="mt-3 tabular-nums">
                <span class="text-muted-foreground line-through">{{ $offer->retail_value_fils->format() }}</span>
                <span class="ms-2 text-2xl font-bold text-primary">{{ $offer->price_fils->format() }}</span>
            </p>

            <dl class="mt-2 space-y-0.5 text-sm">
                <div class="flex gap-2">
                    <dt class="text-muted-foreground">{{ __('Expires') }}</dt>
                    <dd class="font-medium text-accent">{{ $offer->expires_on->format('D d M') }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-muted-foreground">{{ __('Collect by') }}</dt>
                    <dd class="font-medium tabular-nums">{{ $offer->pickup_end->format('H:i') }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-muted-foreground">{{ __('Left') }}</dt>
                    <dd class="font-medium tabular-nums">{{ $offer->remaining }}</dd>
                </div>
            </dl>

            <button type="button" wire:click="reserve({{ $offer->id }}, 1)"
                    class="mt-3 min-h-11 w-full rounded-lg bg-primary px-4 font-bold text-on-primary
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                <span wire:loading.remove wire:target="reserve({{ $offer->id }}, 1)">
                    {{ __('Reserve one') }}
                </span>
                <span wire:loading wire:target="reserve({{ $offer->id }}, 1)">{{ __('Reserving…') }}</span>
            </button>
        </article>
    @empty
        <p class="rounded-xl bg-card p-6 text-center text-muted-foreground">
            {{ __('Nothing available right now. Shops list towards the end of the day.') }}
        </p>
    @endforelse
</div>
