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
use InvalidArgumentException;

class ReserveOffer
{
    public function handle(Offer $offer, User $user, int $qty): Reservation
    {
        if ($qty < 1) {
            throw new InvalidArgumentException("qty must be at least 1, got {$qty}.");
        }

        if ($user->blocked_until && $user->blocked_until->isFuture()) {
            throw ReservationFailed::blocked();
        }

        if ($offer->status !== OfferStatus::Active || $offer->pickup_end->isPast()) {
            throw ReservationFailed::notAvailable();
        }

        // Collected and no-show reservations still count against the cap —
        // only a cancellation returns stock, so only a cancellation should
        // return the customer's allowance.
        $alreadyHeld = $offer->reservations()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                ReservationStatus::Reserved,
                ReservationStatus::Collected,
                ReservationStatus::NoShow,
            ])
            ->sum('qty');

        if ($alreadyHeld + $qty > $offer->max_per_customer) {
            throw ReservationFailed::overCap($offer->max_per_customer);
        }

        return DB::transaction(function () use ($offer, $user, $qty) {
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
        });
    }

    /**
     * Scoped to the store, not the offer, because that is how the code is
     * redeemed: CollectReservation looks up store + code. Two live offers in
     * the same shop sharing a code would let the merchant mark the wrong
     * customer collected and refuse the right one.
     *
     * Only `reserved` rows collide. Once a reservation is collected, cancelled
     * or a no-show its code is dead and free to reissue — which is also why the
     * unique index stays on (offer_id, pickup_code) rather than tightening.
     */
    private function uniqueCodeFor(Offer $offer): string
    {
        do {
            $code = PickupCode::generate();
        } while (
            Reservation::query()
                ->where('pickup_code', $code)
                ->where('status', ReservationStatus::Reserved)
                ->whereRelation('offer', 'store_id', $offer->store_id)
                ->exists()
        );

        return $code;
    }
}
