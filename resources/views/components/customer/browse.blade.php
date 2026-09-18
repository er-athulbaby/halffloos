<?php

use App\Actions\ReserveOffer;
use App\Enums\OfferCategory;
use App\Enums\OfferStatus;
use App\Enums\StoreStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Offer;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public ?string $category = null;

    public ?string $failed = null;
    public ?string $reservedCode = null;
    public ?string $reservedTitle = null;

    public function filterBy(?string $category): void
    {
        $this->category = $this->category === $category ? null : $category;
    }

    public function reserve(int $offerId, int $qty = 1): void
    {
        $this->failed = null;
        $this->reservedCode = null;
        $this->reservedTitle = null;

        $offer = Offer::findOrFail($offerId);

        try {
            $reservation = (new ReserveOffer)->handle($offer, auth()->user(), $qty);
        } catch (ReservationFailed $e) {
            $this->failed = $e->getMessage();

            return;
        }

        $this->reservedCode = $reservation->pickup_code;
        $this->reservedTitle = $offer->title;
    }

    public function with(): array
    {
        $offers = Offer::query()
            ->where('status', OfferStatus::Active)
            ->where('pickup_end', '>', now())
            ->whereRelation('store', 'status', StoreStatus::Approved)
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)
                    ->orWhereRelation('store', 'name', 'like', $term)
                    ->orWhereRelation('store', 'area', 'like', $term));
            })
            ->with('store')
            ->orderBy('pickup_end')
            ->get();

        return [
            'categories' => OfferCategory::cases(),
            // Grouped by shop so each reads as a storefront, but the offers stay
            // on screen: a shop may have one thing expiring tonight, and hiding
            // that behind a tap would waste the trip.
            'shops' => $offers->groupBy(fn (Offer $o) => $o->store->id),
            'total' => $offers->count(),
        ];
    }
};
?>

<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold tracking-tight">{{ __("Save on food near you") }}</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">{{ __('Reserve now, pay at the shop when you collect.') }}</p>
    </div>

    @if ($reservedCode)
        <div class="rounded-2xl border-2 border-primary bg-card p-4" role="status">
            <div class="flex items-center gap-2 text-primary">
                <x-icon name="check-circle" class="size-5" />
                <p class="text-sm font-semibold">{{ __('Reserved — :item', ['item' => $reservedTitle]) }}</p>
            </div>
            <p class="mt-2 font-mono text-4xl font-bold tracking-[0.2em] tabular-nums">{{ $reservedCode }}</p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ __('Show this at the shop. It is saved under My codes.') }}
            </p>
        </div>
    @endif

    @if ($failed)
        <div class="flex items-start gap-2 rounded-xl border border-destructive bg-card p-3 text-sm" role="alert">
            <x-icon name="warning-circle" class="size-5 text-destructive" />
            <span>{{ $failed }}</span>
        </div>
    @endif

    {{-- Search --}}
    <div class="relative">
        <x-icon name="magnifying-glass"
                class="pointer-events-none absolute inset-y-0 start-3 my-auto size-5 text-muted-foreground" />
        <label for="q" class="sr-only">{{ __('Search food or shops') }}</label>
        <input id="q" type="search" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('Search food or shops') }}"
               class="block min-h-12 w-full rounded-xl border border-border-subtle bg-card ps-11 pe-3
                      placeholder:text-muted-foreground
                      focus:border-primary focus:ring-2 focus:ring-primary">
    </div>

    {{-- Category chips --}}
    <div class="-mx-4 overflow-x-auto px-4">
        <div class="flex w-max gap-2 pb-1">
            @foreach ($categories as $case)
                @php($on = $category === $case->value)
                <button type="button" wire:click="filterBy('{{ $case->value }}')"
                        aria-pressed="{{ $on ? 'true' : 'false' }}"
                        @class([
                            'flex min-h-11 shrink-0 items-center gap-2 rounded-full border px-3.5 text-sm font-medium',
                            'transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                            'border-primary bg-primary text-on-primary' => $on,
                            'border-border-subtle bg-card text-foreground' => ! $on,
                        ])>
                    <x-icon :name="$case->icon()" class="size-4" />
                    {{ $case->label() }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($category || trim($search) !== '')
        <div class="flex items-center gap-3 text-sm text-muted-foreground">
            <span>{{ trans_choice('{0}No matches|{1}1 item|[2,*]:count items', $total, ['count' => $total]) }}</span>
            <button type="button" wire:click="$set('category', null); $set('search', '')"
                    class="min-h-11 font-medium text-primary underline underline-offset-4">
                {{ __('Clear') }}
            </button>
        </div>
    @endif

    {{-- Shops --}}
    @forelse ($shops as $offers)
        @php($shop = $offers->first()->store)
        <section class="overflow-hidden rounded-2xl border border-border-subtle bg-card">
            <header class="flex items-center gap-3 px-4 py-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                    <x-icon name="storefront" class="size-5" />
                </div>
                <div class="min-w-0">
                    <h2 class="truncate font-semibold leading-tight">{{ $shop->name }}</h2>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ $shop->area }} · {{ trans_choice('{1}:count item|[2,*]:count items', $offers->count(), ['count' => $offers->count()]) }}
                    </p>
                </div>
            </header>

            @foreach ($offers as $offer)
                @php($percent = $offer->price_fils->percentOffFrom($offer->retail_value_fils))
                @php($soon = $offer->expires_on->isToday() || $offer->expires_on->isTomorrow())
                <article class="flex items-center gap-3 border-t border-border-subtle p-3">
                    <div class="relative">
                        <x-offer-image :offer="$offer" size="size-16" />
                        <span class="absolute bottom-0 start-0 rounded-bl-xl rounded-tr-md bg-primary px-1.5
                                     text-[11px] font-bold leading-4 text-on-primary tabular-nums">
                            −{{ $percent }}%
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-sm font-semibold leading-tight">{{ $offer->title }}</h3>

                        <p class="flex items-baseline gap-1.5 tabular-nums">
                            <span class="text-lg font-bold leading-tight text-primary">{{ $offer->price_fils->format() }}</span>
                            <span class="text-xs text-muted-foreground line-through">{{ $offer->retail_value_fils->toDecimal() }}</span>
                        </p>

                        <p class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                            <span @class(['font-semibold text-accent' => $soon])>
                                {{ $offer->expires_on->format('D d M') }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="clock" class="size-3.5" />{{ $offer->pickup_end->format('H:i') }}
                            </span>
                            <span>{{ __(':n left', ['n' => $offer->remaining]) }}</span>
                        </p>
                    </div>

                    <button type="button" wire:click="reserve({{ $offer->id }}, 1)"
                            class="min-h-11 shrink-0 rounded-xl bg-primary px-4 text-sm font-bold text-on-primary
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        <span wire:loading.remove wire:target="reserve({{ $offer->id }}, 1)">{{ __('Reserve') }}</span>
                        <span wire:loading wire:target="reserve({{ $offer->id }}, 1)">{{ __('…') }}</span>
                    </button>
                </article>
            @endforeach
        </section>
    @empty
        <div class="rounded-2xl border border-border-subtle bg-card p-8 text-center">
            <x-icon name="storefront" class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-3 font-medium">
                @if ($category || trim($search) !== '')
                    {{ __('Nothing matches that') }}
                @else
                    {{ __('Nothing available right now') }}
                @endif
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                @if ($category || trim($search) !== '')
                    {{ __('Try another category, or clear the search.') }}
                @else
                    {{ __('Shops list what is left towards the end of the day.') }}
                @endif
            </p>
        </div>
    @endforelse
</div>
