<?php

use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\StoreStatus;
use App\Models\Offer;
use App\Models\Store;
use App\Rules\AtLeastHalfOff;
use App\Support\Money;
use Livewire\Component;

new class extends Component
{
    public Store $store;

    public string $title = '';
    public string $expires_on = '';
    public string $retail_value = '';
    public string $price = '';
    public int $quantity = 1;
    public string $pickup_end = '';

    public function mount(): void
    {
        $store = auth()->user()?->stores()->where('status', StoreStatus::Approved)->first();

        abort_if($store === null, 403, __('Your shop is not approved yet.'));

        $this->store = $store;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->title = '';
        $this->retail_value = '';
        $this->price = '';
        $this->quantity = 1;
        $this->expires_on = now()->addDay()->toDateString();
        $this->pickup_end = '22:00';
    }

    /** Live percentage shown under the price field as the merchant types. */
    public function getPercentOffProperty(): ?int
    {
        try {
            $retail = Money::fromString($this->retail_value);
            $price = Money::fromString($this->price);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $retail->fils() === 0 ? null : $price->percentOffFrom($retail);
    }

    public function save(): void
    {
        $money = ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/'];

        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'expires_on' => ['required', 'date', 'after_or_equal:today'],
            'retail_value' => $money,
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'pickup_end' => ['required', 'date_format:H:i'],
        ], [], [
            'retail_value' => __('original price'),
            'pickup_end' => __('collect by'),
        ]);

        // The 50% floor needs the retail value, so it is validated second.
        $this->validate([
            'price' => [...$money, new AtLeastHalfOff(Money::fromString($this->retail_value))],
        ]);

        $end = now()->setTimeFromTimeString($this->pickup_end);

        if ($end->isPast()) {
            $end = $end->addDay();
        }

        Offer::create([
            'store_id' => $this->store->id,
            'type' => OfferType::Item,
            'title' => $this->title,
            'retail_value_fils' => Money::fromString($this->retail_value),
            'price_fils' => Money::fromString($this->price),
            'quantity' => $this->quantity,
            'remaining' => $this->quantity,
            'max_per_customer' => 2,
            'expires_on' => $this->expires_on,
            'pickup_start' => now(),
            'pickup_end' => $end,
            'status' => OfferStatus::Active,
        ]);

        $this->resetForm();
        session()->flash('listed', __('Listed. Customers nearby can see it now.'));
    }

    public function repeatYesterday(int $offerId): void
    {
        $previous = $this->store->offers()->whereKey($offerId)->firstOrFail();

        $this->title = $previous->title;
        $this->retail_value = (string) $previous->retail_value_fils->toDecimal();
        $this->price = (string) $previous->price_fils->toDecimal();
        $this->quantity = $previous->quantity;
        $this->expires_on = now()->addDay()->toDateString();
        $this->pickup_end = $previous->pickup_end->format('H:i');
    }

    public function with(): array
    {
        return [
            'live' => $this->store->offers()
                ->whereIn('status', [OfferStatus::Active, OfferStatus::SoldOut])
                ->orderByDesc('id')
                ->get(),
            'recent' => $this->store->offers()
                ->where('status', OfferStatus::Closed)
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold tracking-tight">{{ $store->name }}</h1>
        <p class="text-sm text-muted-foreground">{{ __('List what is left before you close.') }}</p>
    </div>

    @if (session('listed'))
        <div class="rounded-lg bg-primary px-4 py-3 font-medium text-on-primary" role="status">
            {{ session('listed') }}
        </div>
    @endif

    {{-- List an item --}}
    <form wire:submit="save" class="space-y-3 rounded-xl bg-card p-4 shadow-sm">
        <div>
            <label for="title" class="block text-sm font-medium">{{ __('What is it?') }}</label>
            <input id="title" type="text" wire:model="title" required
                   placeholder="{{ __('Fresh milk 1L') }}"
                   class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle px-3
                          focus:border-primary focus:ring-2 focus:ring-primary">
            @error('title') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="retail" class="block text-sm font-medium">{{ __('Normal price') }}</label>
                <input id="retail" type="text" inputmode="decimal" wire:model.live.debounce.400ms="retail_value"
                       placeholder="2.000"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle px-3 tabular-nums
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('retail_value') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="price" class="block text-sm font-medium">{{ __('Your price') }}</label>
                <input id="price" type="text" inputmode="decimal" wire:model.live.debounce.400ms="price"
                       placeholder="0.500"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle px-3 tabular-nums
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('price') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($this->percentOff !== null)
            <p @class([
                'text-sm font-medium',
                'text-primary' => $this->percentOff >= 50,
                'text-destructive' => $this->percentOff < 50,
            ])>
                @if ($this->percentOff >= 50)
                    {{ __(':percent% off — good to list.', ['percent' => $this->percentOff]) }}
                @else
                    {{ __('Only :percent% off. Halffloos needs at least 50%.', ['percent' => $this->percentOff]) }}
                @endif
            </p>
        @endif

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label for="quantity" class="block text-sm font-medium">{{ __('How many') }}</label>
                <input id="quantity" type="number" min="1" inputmode="numeric" wire:model="quantity"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle px-3 tabular-nums
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('quantity') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="expires" class="block text-sm font-medium">{{ __('Expires') }}</label>
                <input id="expires" type="date" wire:model="expires_on"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle px-3
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('expires_on') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="pickup" class="block text-sm font-medium">{{ __('Collect by') }}</label>
                <input id="pickup" type="time" wire:model="pickup_end"
                       class="mt-1 block min-h-11 w-full rounded-lg border border-border-subtle px-3
                              focus:border-primary focus:ring-2 focus:ring-primary">
                @error('pickup_end') <p class="mt-1 text-sm text-destructive">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit"
                class="min-h-11 w-full rounded-lg bg-primary px-4 font-bold text-on-primary
                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            <span wire:loading.remove wire:target="save">{{ __('List it') }}</span>
            <span wire:loading wire:target="save">{{ __('Listing…') }}</span>
        </button>
    </form>

    {{-- Live today --}}
    <section>
        <h2 class="text-sm font-bold uppercase tracking-wide text-muted-foreground">{{ __('Live now') }}</h2>

        @forelse ($live as $offer)
            <div class="mt-2 flex items-center justify-between rounded-xl bg-card p-3 shadow-sm">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ $offer->title }}</p>
                    <p class="text-sm text-muted-foreground tabular-nums">
                        <span class="line-through">{{ $offer->retail_value_fils->format() }}</span>
                        <span class="font-bold text-primary">{{ $offer->price_fils->format() }}</span>
                        · {{ __('collect by :time', ['time' => $offer->pickup_end->format('H:i')]) }}
                    </p>
                </div>
                <div class="ms-3 shrink-0 text-end">
                    <p class="text-lg font-bold tabular-nums">{{ $offer->remaining }}</p>
                    <p class="text-xs text-muted-foreground">{{ __('of :total left', ['total' => $offer->quantity]) }}</p>
                </div>
            </div>
        @empty
            <p class="mt-2 text-sm text-muted-foreground">{{ __('Nothing listed yet today.') }}</p>
        @endforelse
    </section>

    {{-- Repeat something from before --}}
    @if ($recent->isNotEmpty())
        <section>
            <h2 class="text-sm font-bold uppercase tracking-wide text-muted-foreground">{{ __('List again') }}</h2>

            @foreach ($recent as $offer)
                <button type="button" wire:click="repeatYesterday({{ $offer->id }})"
                        class="mt-2 flex min-h-11 w-full items-center justify-between rounded-xl bg-muted px-3 py-2 text-start
                               focus:outline-none focus:ring-2 focus:ring-primary">
                    <span class="truncate">{{ $offer->title }}</span>
                    <span class="ms-3 shrink-0 text-sm font-medium text-primary">{{ __('Repeat') }}</span>
                </button>
            @endforeach
        </section>
    @endif
</div>
