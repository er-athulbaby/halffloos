<?php

namespace Tests\Feature;

use App\Actions\CancelReservation;
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

class CancelReservationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email = 'c@example.test'): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => $email,
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);
    }

    private function offer(int $remaining = 10, int $maxPerCustomer = 2): Offer
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'm'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        $store = Store::create([
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
            'store_id' => $store->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => $remaining,
            'remaining' => $remaining,
            'max_per_customer' => $maxPerCustomer,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->subHour(),
            'pickup_end' => now()->addHour(),
            'status' => OfferStatus::Active,
        ]);
    }

    public function test_it_restores_stock(): void
    {
        $offer = $this->offer(remaining: 10);
        $user = $this->customer();

        $reservation = (new ReserveOffer)->handle($offer, $user, 2);
        $this->assertSame(8, $offer->fresh()->remaining);

        $cancelled = (new CancelReservation)->handle($reservation, $user);

        $this->assertSame(ReservationStatus::Cancelled, $cancelled->status);
        $this->assertSame(10, $offer->fresh()->remaining);
    }

    // The whole point of the feature: without this the units go in the bin.
    public function test_a_sold_out_offer_goes_back_to_active(): void
    {
        $offer = $this->offer(remaining: 2, maxPerCustomer: 2);
        $user = $this->customer();

        $reservation = (new ReserveOffer)->handle($offer, $user, 2);
        $this->assertSame(OfferStatus::SoldOut, $offer->fresh()->status);

        (new CancelReservation)->handle($reservation, $user);

        $this->assertSame(OfferStatus::Active, $offer->fresh()->status);
        $this->assertSame(2, $offer->fresh()->remaining);
    }

    public function test_a_closed_offer_stays_closed(): void
    {
        $offer = $this->offer(remaining: 2, maxPerCustomer: 2);
        $user = $this->customer();

        $reservation = (new ReserveOffer)->handle($offer, $user, 2);
        $offer->update(['status' => OfferStatus::Closed, 'pickup_end' => now()->subHour()]);

        (new CancelReservation)->handle($reservation, $user);

        $this->assertSame(OfferStatus::Closed, $offer->fresh()->status);
        $this->assertSame(2, $offer->fresh()->remaining);
    }

    public function test_a_sold_out_offer_past_its_window_does_not_reopen(): void
    {
        $offer = $this->offer(remaining: 2, maxPerCustomer: 2);
        $user = $this->customer();

        $reservation = (new ReserveOffer)->handle($offer, $user, 2);
        $offer->update(['pickup_end' => now()->subHour()]);

        (new CancelReservation)->handle($reservation, $user);

        $this->assertSame(OfferStatus::SoldOut, $offer->fresh()->status);
    }

    public function test_it_frees_the_per_customer_cap(): void
    {
        $offer = $this->offer(remaining: 10, maxPerCustomer: 2);
        $user = $this->customer();

        $reservation = (new ReserveOffer)->handle($offer, $user, 2);
        (new CancelReservation)->handle($reservation, $user);

        $again = (new ReserveOffer)->handle($offer->fresh(), $user, 2);

        $this->assertSame(2, $again->qty);
        $this->assertSame(8, $offer->fresh()->remaining);
    }

    public function test_it_refuses_another_users_reservation(): void
    {
        $offer = $this->offer();
        $reservation = (new ReserveOffer)->handle($offer, $this->customer('a@example.test'), 1);

        $this->expectException(ReservationFailed::class);

        (new CancelReservation)->handle($reservation, $this->customer('b@example.test'));
    }

    public function test_it_refuses_a_collected_reservation(): void
    {
        $offer = $this->offer();
        $user = $this->customer();
        $reservation = (new ReserveOffer)->handle($offer, $user, 1);
        $reservation->update(['status' => ReservationStatus::Collected, 'collected_at' => now()]);

        $this->expectException(ReservationFailed::class);

        (new CancelReservation)->handle($reservation, $user);
    }

    public function test_it_refuses_a_second_cancellation(): void
    {
        $offer = $this->offer(remaining: 10);
        $user = $this->customer();
        $reservation = (new ReserveOffer)->handle($offer, $user, 2);

        (new CancelReservation)->handle($reservation, $user);

        try {
            (new CancelReservation)->handle($reservation->fresh(), $user);
            $this->fail('Expected the second cancellation to fail.');
        } catch (ReservationFailed) {
            // expected
        }

        // And the stock was not handed back twice.
        $this->assertSame(10, $offer->fresh()->remaining);
    }
}
