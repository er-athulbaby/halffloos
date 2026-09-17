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

    /**
     * A customer who turns up at 22:02 for a 22:00 window and is served at 22:06
     * is not a no-show — but this job runs every five minutes, and two no-shows
     * block an account for seven days with nothing to reverse it. The grace
     * period is the gap between the window closing and the queue at the till
     * clearing.
     */
    private const GRACE_MINUTES = 30;

    public function handle(): int
    {
        $expired = Offer::query()
            ->where('pickup_end', '<', now()->subMinutes(self::GRACE_MINUTES))
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
