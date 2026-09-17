<?php

namespace App\Actions;

use App\Enums\OfferStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\User;
use App\Support\PickupCode;
use Illuminate\Support\Facades\DB;

class ReserveOffer
{
    public function handle(Offer $offer, User $user, int $qty): Reservation
    {
        if ($user->blocked_until && $user->blocked_until->isFuture()) {
            throw ReservationFailed::blocked();
        }

        if ($offer->status !== OfferStatus::Active || $offer->pickup_end->isPast()) {
            throw ReservationFailed::notAvailable();
        }

        $alreadyHeld = $offer->reservations()
            ->where('user_id', $user->id)
            ->where('status', ReservationStatus::Reserved)
            ->sum('qty');

        if ($alreadyHeld + $qty > $offer->max_per_customer) {
            throw ReservationFailed::overCap($offer->max_per_customer);
        }

        // The whole concurrency story: one conditional UPDATE. Zero affected
        // rows means someone else took the last of the stock first.
        $claimed = DB::table('offers')
            ->where('id', $offer->id)
            ->where('remaining', '>=', $qty)
            ->decrement('remaining', $qty);

        if ($claimed === 0) {
            throw ReservationFailed::soldOut();
        }

        $reservation = Reservation::create([
            'offer_id' => $offer->id,
            'user_id' => $user->id,
            'qty' => $qty,
            'pickup_code' => $this->uniqueCodeFor($offer),
            'status' => ReservationStatus::Reserved,
        ]);

        if ($offer->fresh()->remaining === 0) {
            $offer->update(['status' => OfferStatus::SoldOut]);
        }

        return $reservation;
    }

    private function uniqueCodeFor(Offer $offer): string
    {
        do {
            $code = PickupCode::generate();
        } while ($offer->reservations()->where('pickup_code', $code)->exists());

        return $code;
    }
}
