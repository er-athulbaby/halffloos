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

    public function filterBy(?string $category): void
    {
        $this->category = $this->category === $category ? null : $category;
    }

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
            // Grouped by shop so each one reads as a storefront, but the offers
            // stay on screen — a shop may only have one thing expiring tonight,
            // and hiding that behind a tap would waste the trip.
            'shops' => $offers->groupBy(fn (Offer $o) => $o->store->id),
            'total' => $offers->count(),
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

    {{-- Search --}}
    <label class="block">
        <span class="sr-only">{{ __('Search food or shops') }}</span>
        <input type="search" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('Search food or shops') }}"
               class="block min-h-11 w-full rounded-xl border border-border-subtle bg-card px-4
                      focus:border-primary focus:ring-2 focus:ring-primary">
    </label>

    {{-- Category chips --}}
    <div class="-mx-4 overflow-x-auto px-4">
        <div class="flex w-max gap-2 pb-1">
            @foreach ($categories as $case)
                <button type="button" wire:click="filterBy('{{ $case->value }}')"
                        aria-pressed="{{ $category === $case->value ? 'true' : 'false' }}"
                        @class([
                            'flex min-h-11 shrink-0 items-center gap-2 rounded-xl px-3 text-sm font-medium',
                            'focus:outline-none focus:ring-2 focus:ring-primary',
                            'bg-primary text-on-primary' => $category === $case->value,
                            'bg-card text-foreground' => $category !== $case->value,
                        ])>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="{{ $case->icon() }}"/>
                    </svg>
                    {{ $case->label() }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($category || trim($search) !== '')
        <p class="text-sm text-muted-foreground">
            {{ trans_choice('{0}Nothing matches.|{1}1 item|[2,*]:count items', $total, ['count' => $total]) }}
            <button type="button" wire:click="$set('category', null); $set('search', '')"
                    class="ms-2 font-medium text-primary underline underline-offset-4">
                {{ __('Clear') }}
            </button>
        </p>
    @endif

    {{-- Shops --}}
    @forelse ($shops as $offers)
        @php($shop = $offers->first()->store)
        <section class="overflow-hidden rounded-xl bg-card shadow-sm">
            <header class="flex items-center gap-3 border-b border-border-subtle p-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary
                            text-lg font-bold text-on-primary">
                    {{ mb_substr($shop->name, 0, 1) }}
                </div>
                <div class="min-w-0">
                    <h2 class="truncate font-bold">{{ $shop->name }}</h2>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ $shop->area }} · {{ trans_choice('{1}:count item left|[2,*]:count items left', $offers->count(), ['count' => $offers->count()]) }}
                    </p>
                </div>
            </header>

            @foreach ($offers as $offer)
                @php($percent = $offer->price_fils->percentOffFrom($offer->retail_value_fils))
                <article @class([
                    'flex items-start gap-3 p-4',
                    'border-t border-border-subtle' => ! $loop->first,
                ])>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-medium">{{ $offer->title }}</h3>
                        <p class="text-xs text-muted-foreground">{{ $offer->category->label() }}</p>

                        <p class="mt-1 tabular-nums">
                            <span class="text-muted-foreground line-through">{{ $offer->retail_value_fils->format() }}</span>
                            <span class="ms-1 text-xl font-bold text-primary">{{ $offer->price_fils->format() }}</span>
                        </p>

                        <p class="mt-1 text-sm">
                            <span class="font-medium text-accent">
                                {{ __('Expires :date', ['date' => $offer->expires_on->format('D d M')]) }}
                            </span>
                            <span class="text-muted-foreground">
                                · {{ __('collect by :time', ['time' => $offer->pickup_end->format('H:i')]) }}
                                · {{ __(':n left', ['n' => $offer->remaining]) }}
                            </span>
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <span class="rounded-lg bg-primary px-2 py-1 text-sm font-bold text-on-primary tabular-nums">
                            {{ __(':percent% off', ['percent' => $percent]) }}
                        </span>
                        <button type="button" wire:click="reserve({{ $offer->id }}, 1)"
                                class="min-h-11 rounded-lg bg-primary px-4 font-bold text-on-primary
                                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            <span wire:loading.remove wire:target="reserve({{ $offer->id }}, 1)">{{ __('Reserve') }}</span>
                            <span wire:loading wire:target="reserve({{ $offer->id }}, 1)">{{ __('…') }}</span>
                        </button>
                    </div>
                </article>
            @endforeach
        </section>
    @empty
        <p class="rounded-xl bg-card p-6 text-center text-muted-foreground">
            @if ($category || trim($search) !== '')
                {{ __('Nothing matches that. Try another category.') }}
            @else
                {{ __('Nothing available right now. Shops list towards the end of the day.') }}
            @endif
        </p>
    @endforelse
</div>
