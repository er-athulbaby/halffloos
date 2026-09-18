<?php

use App\Actions\CollectReservation;
use App\Enums\StoreStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Store;
use Livewire\Component;

new class extends Component
{
    public Store $store;

    public string $code = '';
    public ?string $failed = null;
    public ?string $collected = null;

    public function mount(): void
    {
        $store = auth()->user()?->stores()->where('status', StoreStatus::Approved)->first();

        abort_if($store === null, 403, __('Your shop is not approved yet.'));

        $this->store = $store;
    }

    public function collect(): void
    {
        $this->failed = null;
        $this->collected = null;

        try {
            $reservation = (new CollectReservation)->handle($this->store, $this->code);
        } catch (ReservationFailed $e) {
            $this->failed = $e->getMessage();
            $this->code = '';

            return;
        }

        $reservation->loadMissing('offer');

        $this->collected = __(':qty × :title — :total', [
            'qty' => $reservation->qty,
            'title' => $reservation->offer->title,
            'total' => \App\Support\Money::fromFils(
                $reservation->offer->price_fils->fils() * $reservation->qty
            )->format(),
        ]);

        $this->code = '';
    }
};
?>

<div class="space-y-4">
    <div>
        <h1 class="text-xl font-bold tracking-tight">{{ __('Collections') }}</h1>
        <p class="text-sm text-muted-foreground">{{ __('Type the code the customer shows you.') }}</p>
    </div>

    @if ($collected)
        <div class="rounded-xl bg-primary p-4 text-on-primary" role="status">
            <p class="font-medium">{{ __('Hand it over and take payment:') }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $collected }}</p>
        </div>
    @endif

    @if ($failed)
        <div class="rounded-lg bg-destructive px-4 py-3 font-medium text-white" role="alert">
            {{ $failed }}
        </div>
    @endif

    <form wire:submit="collect" class="rounded-xl bg-card p-4 shadow-sm">
        <label for="code" class="block text-sm font-medium">{{ __('Pickup code') }}</label>
        <input id="code" type="text" wire:model="code" required autofocus
               maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false"
               placeholder="AB23CD"
               class="mt-1 block h-16 w-full rounded-lg border border-border-subtle px-3 text-center
                      text-3xl font-bold uppercase tracking-widest tabular-nums
                      focus:border-primary focus:ring-2 focus:ring-primary">

        <button type="submit"
                class="mt-3 min-h-11 w-full rounded-lg bg-primary px-4 font-bold text-on-primary
                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            {{ __('Collect') }}
        </button>
    </form>
</div>
