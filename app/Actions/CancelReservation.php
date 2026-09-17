<?php

namespace App\Actions;

use App\Enums\OfferStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelReservation
{
    public function handle(Reservation $reservation, User $user): Reservation
    {
        return DB::transaction(function () use ($reservation, $user) {
            if ($reservation->user_id !== $user->id) {
                throw ReservationFailed::notYours();
            }

            if ($reservation->status === ReservationStatus::Collected) {
                throw ReservationFailed::alreadyCollected();
            }

            if ($reservation->status !== ReservationStatus::Reserved) {
                throw ReservationFailed::notAvailable();
            }

            // The mirror image of the reservation decrement: one conditional
            // UPDATE that can never push stock past what was originally listed.
            // Zero affected rows means the invariant would have broken, so roll
            // the whole cancellation back rather than invent stock.
            $restored = DB::table('offers')
                ->where('id', $reservation->offer_id)
                ->whereRaw('remaining + ? <= quantity', [$reservation->qty])
                ->increment('remaining', $reservation->qty);

            if ($restored === 0) {
                throw ReservationFailed::notAvailable();
            }

            $offer = $reservation->offer()->first();

            // Stock is back, so the offer is buyable again — unless the pickup
            // window has already passed, in which case `closed` stands and the
            // units are genuinely gone.
            if ($offer->status === OfferStatus::SoldOut && $offer->pickup_end->isFuture()) {
                $offer->update(['status' => OfferStatus::Active]);
            }

            $reservation->update(['status' => ReservationStatus::Cancelled]);

            return $reservation->fresh();
        });
    }
}
