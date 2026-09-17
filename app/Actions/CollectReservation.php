<?php

namespace App\Actions;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Reservation;
use App\Models\Store;

class CollectReservation
{
    public function handle(Store $store, string $code): Reservation
    {
        $candidates = Reservation::query()
            ->whereRelation('offer', 'store_id', $store->id)
            ->where('pickup_code', strtoupper(trim($code)))
            ->get();

        $reservation = $candidates->firstWhere('status', ReservationStatus::Reserved)
            ?? $candidates->first();

        if (! $reservation) {
            throw ReservationFailed::unknownCode();
        }

        if ($reservation->status === ReservationStatus::Collected) {
            throw ReservationFailed::alreadyCollected();
        }

        if ($reservation->status !== ReservationStatus::Reserved) {
            throw ReservationFailed::notAvailable();
        }

        $reservation->update([
            'status' => ReservationStatus::Collected,
            'collected_at' => now(),
        ]);

        return $reservation->fresh();
    }
}
