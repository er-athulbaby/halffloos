<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\ReservationStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseExpiredOffersTest extends TestCase
{
    use RefreshDatabase;

    private function expiredOfferWithReservation(): array
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'm@example.test',
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

        $offer = Offer::create([
            'store_id' => $store->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => 10,
            'remaining' => 5,
            'max_per_customer' => 2,
            'expires_on' => now()->toDateString(),
            'pickup_start' => now()->subHours(3),
            'pickup_end' => now()->subHour(),
            'status' => OfferStatus::Active,
        ]);

        $customer = User::create([
            'name' => 'Customer',
            'email' => 'c@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);

        $reservation = Reservation::create([
            'offer_id' => $offer->id,
            'user_id' => $customer->id,
            'qty' => 1,
            'pickup_code' => 'ABC234',
            'status' => ReservationStatus::Reserved,
        ]);

        return [$offer, $reservation, $customer];
    }

    public function test_it_closes_offers_past_their_window(): void
    {
        [$offer] = $this->expiredOfferWithReservation();

        $this->artisan('halffloos:close-expired')->assertSuccessful();

        $this->assertSame(OfferStatus::Closed, $offer->fresh()->status);
    }

    public function test_it_marks_uncollected_reservations_as_no_shows(): void
    {
        [, $reservation, $customer] = $this->expiredOfferWithReservation();

        $this->artisan('halffloos:close-expired')->assertSuccessful();

        $this->assertSame(ReservationStatus::NoShow, $reservation->fresh()->status);
        $this->assertSame(1, $customer->fresh()->no_show_count);
        $this->assertNull($customer->fresh()->blocked_until);
    }

    public function test_a_second_no_show_blocks_for_seven_days(): void
    {
        [, $reservation, $customer] = $this->expiredOfferWithReservation();
        $customer->update(['no_show_count' => 1]);

        $this->artisan('halffloos:close-expired')->assertSuccessful();

        $this->assertSame(2, $customer->fresh()->no_show_count);
        $this->assertTrue($customer->fresh()->blocked_until->isFuture());
        $this->assertSame(7, (int) round(now()->diffInDays($customer->fresh()->blocked_until)));
        $this->assertSame(ReservationStatus::NoShow, $reservation->fresh()->status);
    }

    public function test_it_leaves_collected_reservations_alone(): void
    {
        [, $reservation, $customer] = $this->expiredOfferWithReservation();
        $reservation->update(['status' => ReservationStatus::Collected, 'collected_at' => now()]);

        $this->artisan('halffloos:close-expired')->assertSuccessful();

        $this->assertSame(ReservationStatus::Collected, $reservation->fresh()->status);
        $this->assertSame(0, $customer->fresh()->no_show_count);
    }
}
