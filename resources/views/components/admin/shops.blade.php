<?php

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Store;
use Livewire\Component;

new class extends Component
{
    public ?string $done = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === UserRole::Admin, 403);
    }

    /**
     * Approval is the gate the whole liability story rests on: a shop is only
     * approved once its CR number and Ministry of Health food licence have been
     * checked against Sijilat and the licence register. That check happens
     * outside this screen, by a person. This only records the outcome.
     */
    public function approve(int $storeId): void
    {
        $store = Store::findOrFail($storeId);

        $store->update([
            'status' => StoreStatus::Approved,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        $this->done = __(':shop approved. They can list stock now.', ['shop' => $store->name]);
    }

    public function suspend(int $storeId): void
    {
        $store = Store::findOrFail($storeId);

        $store->update(['status' => StoreStatus::Suspended]);

        $this->done = __(':shop suspended. Their live offers stay up until their windows close.', [
            'shop' => $store->name,
        ]);
    }

    public function with(): array
    {
        $stores = Store::with('user')->withCount('offers')->orderByDesc('id')->get();

        return [
            'pending' => $stores->where('status', StoreStatus::Pending),
            'rest' => $stores->whereNotIn('status', [StoreStatus::Pending]),
        ];
    }
};
?>

<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold tracking-tight">{{ __('Shops') }}</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">
            {{ __('Check the CR and food licence before approving.') }}
        </p>
    </div>

    @if ($done)
        <div class="flex items-start gap-2 rounded-xl border border-border-subtle bg-card p-3 text-sm" role="status">
            <x-icon name="check-circle" class="size-5 text-primary" />
            <span>{{ $done }}</span>
        </div>
    @endif

    <section>
        <h2 class="text-sm font-bold uppercase tracking-wide text-muted-foreground">
            {{ __('Waiting for approval') }}
            @if ($pending->isNotEmpty())
                <span class="ms-1 rounded-md bg-accent px-1.5 text-on-accent tabular-nums">{{ $pending->count() }}</span>
            @endif
        </h2>

        @forelse ($pending as $store)
            <article class="mt-2 rounded-2xl border border-border-subtle bg-card p-4">
                <h3 class="font-semibold">{{ $store->name }}</h3>
                <p class="text-sm text-muted-foreground">{{ $store->area }} · {{ $store->phone }}</p>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('CR number') }}</dt>
                        <dd class="font-medium tabular-nums">{{ $store->cr_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('Food licence') }}</dt>
                        <dd class="font-medium tabular-nums">{{ $store->food_licence_no }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-muted-foreground">{{ __('Contact') }}</dt>
                        <dd class="font-medium">{{ $store->user->name }} · {{ $store->user->email }}</dd>
                    </div>
                </dl>

                <button type="button" wire:click="approve({{ $store->id }})"
                        wire:confirm="{{ __('Only approve once you have checked the CR on Sijilat and the food licence is valid. Continue?') }}"
                        class="mt-3 min-h-11 w-full rounded-xl bg-primary px-4 font-bold text-on-primary
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    {{ __('Approve this shop') }}
                </button>
            </article>
        @empty
            <p class="mt-2 rounded-2xl border border-border-subtle bg-card p-6 text-center text-sm text-muted-foreground">
                {{ __('Nothing waiting.') }}
            </p>
        @endforelse
    </section>

    @if ($rest->isNotEmpty())
        <section>
            <h2 class="text-sm font-bold uppercase tracking-wide text-muted-foreground">{{ __('All shops') }}</h2>

            @foreach ($rest as $store)
                <div class="mt-2 flex items-center gap-3 rounded-xl border border-border-subtle bg-card p-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                        <x-icon name="storefront" class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $store->name }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ $store->area }} · {{ trans_choice('{0}no listings|{1}:count listing|[2,*]:count listings', $store->offers_count, ['count' => $store->offers_count]) }}
                        </p>
                    </div>

                    @if ($store->status === StoreStatus::Approved)
                        <button type="button" wire:click="suspend({{ $store->id }})"
                                wire:confirm="{{ __('Suspend this shop? They will not be able to list anything new.') }}"
                                class="min-h-11 shrink-0 text-sm font-medium text-muted-foreground underline underline-offset-4
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            {{ __('Suspend') }}
                        </button>
                    @else
                        <button type="button" wire:click="approve({{ $store->id }})"
                                class="min-h-11 shrink-0 rounded-lg bg-primary px-3 text-sm font-bold text-on-primary
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            {{ __('Reinstate') }}
                        </button>
                    @endif
                </div>
            @endforeach
        </section>
    @endif
</div>
