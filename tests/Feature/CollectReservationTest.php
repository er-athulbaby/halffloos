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
}
