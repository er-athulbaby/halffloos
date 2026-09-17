<?php

namespace App\Console\Commands;

use App\Enums\OfferStatus;
use App\Enums\ReservationStatus;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Console\Command;

class CloseExpiredOffers extends Command
{
    protected $signature = 'halffloos:close-expired';

    protected $description = 'Close offers whose pickup window has passed and mark uncollected reservations as no-shows';

    public function handle(): int
    {
        $expired = Offer::query()
            ->where('pickup_end', '<', now())
            ->whereIn('status', [OfferStatus::Active, OfferStatus::SoldOut])
            ->get();

        foreach ($expired as $offer) {
            $offer->update(['status' => OfferStatus::Closed]);

            $stranded = Reservation::query()
                ->where('offer_id', $offer->id)
                ->where('status', ReservationStatus::Reserved)
                ->with('user')
                ->get();

            foreach ($stranded as $reservation) {
                $reservation->update(['status' => ReservationStatus::NoShow]);

                $user = $reservation->user;
                $user->increment('no_show_count');

                if ($user->fresh()->no_show_count >= 2) {
                    $user->update(['blocked_until' => now()->addDays(7)]);
                }
            }
        }

        $this->info("Closed {$expired->count()} offers.");

        return self::SUCCESS;
    }
}
