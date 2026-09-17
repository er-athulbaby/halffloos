<?php

namespace Tests\Feature;

use App\Actions\CollectReservation;
use App\Actions\ReserveOffer;
use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\ReservationStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Exceptions\ReservationFailed;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectReservationTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private function seedOffer(): Offer
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'm@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        $this->store = Store::create([
            'user_id' => $merchant->id,
            'name' => 'Hidd Cold Store',
            'area' => 'Hidd',
            'lat' => 26.1550,
            'lng' => 50.6550,
            'phone' => '17000000',
            'cr_number' => '12345-1',
            'food_licence_no' => 'MOH-9988',
            'status' => StoreStatus::Approved,
        ]);

        return Offer::create([
            'store_id' => $this->store->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => 10,
            'remaining' => 10,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->subHour(),
            'pickup_end' => now()->addHour(),
            'status' => OfferStatus::Active,
        ]);
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => 'c@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);
    }

    private function offerAt(Store $store, string $title = 'Fresh milk 1L'): Offer
    {
        return Offer::create([
            'store_id' => $store->id,
            'type' => OfferType::Item,
            'title' => $title,
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => 10,
            'remaining' => 10,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->subHour(),
            'pickup_end' => now()->addHour(),
            'status' => OfferStatus::Active,
        ]);
    }

    private function anotherStore(): Store
    {
        $merchant = User::create([
            'name' => 'Other Merchant',
            'email' => 'm2@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        return Store::create([
            'user_id' => $merchant->id,
            'name' => 'Muharraq Cold Store',
            'area' => 'Muharraq',
            'lat' => 26.2570,
            'lng' => 50.6110,
            'phone' => '17000001',
            'cr_number' => '12345-2',
            'food_licence_no' => 'MOH-9989',
            'status' => StoreStatus::Approved,
        ]);
    }

    public function test_it_marks_a_reservation_collected(): void
    {
        $offer = $this->seedOffer();
        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 1);

        $collected = (new CollectReservation)->handle($this->store, $reservation->pickup_code);

        $this->assertSame(ReservationStatus::Collected, $collected->status);
        $this->assertNotNull($collected->collected_at);
    }

    public function test_it_is_case_insensitive(): void
    {
        $offer = $this->seedOffer();
        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 1);

        $collected = (new CollectReservation)->handle(
            $this->store,
            strtolower($reservation->pickup_code)
        );

        $this->assertSame(ReservationStatus::Collected, $collected->status);
    }

    public function test_it_rejects_an_unknown_code(): void
    {
        $this->seedOffer();

        $this->expectException(ReservationFailed::class);

        (new CollectReservation)->handle($this->store, 'ZZZZZZ');
    }

    public function test_it_rejects_a_code_collected_twice(): void
    {
        $offer = $this->seedOffer();
        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 1);

        (new CollectReservation)->handle($this->store, $reservation->pickup_code);

        $this->expectException(ReservationFailed::class);

        (new CollectReservation)->handle($this->store, $reservation->pickup_code);
    }

    public function test_it_prefers_a_live_reservation_over_a_stale_collected_one_sharing_a_code(): void
    {
        $offerA = $this->seedOffer();
        $offerB = $this->offerAt($this->store, 'Yoghurt 500g');
        $customer = $this->customer();

        $staleCollected = Reservation::create([
            'offer_id' => $offerA->id,
            'user_id' => $customer->id,
            'qty' => 1,
            'pickup_code' => 'SHADOW1',
            'status' => ReservationStatus::Collected,
            'collected_at' => now()->subDay(),
        ]);

        $liveReserved = Reservation::create([
            'offer_id' => $offerB->id,
            'user_id' => $customer->id,
            'qty' => 1,
            'pickup_code' => 'SHADOW1',
            'status' => ReservationStatus::Reserved,
        ]);

        $collected = (new CollectReservation)->handle($this->store, 'SHADOW1');

        $this->assertSame($liveReserved->id, $collected->id);
        $this->assertSame(ReservationStatus::Collected, $collected->status);
        $this->assertSame(ReservationStatus::Collected, $staleCollected->fresh()->status);
    }

    public function test_it_rejects_collection_attempted_at_a_different_store(): void
    {
        $offer = $this->seedOffer();
        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 1);

        $otherStore = $this->anotherStore();

        $this->expectException(ReservationFailed::class);

        (new CollectReservation)->handle($otherStore, $reservation->pickup_code);
    }
}
