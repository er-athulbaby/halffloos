<?php

namespace Tests\Feature;

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
use InvalidArgumentException;
use Tests\TestCase;

class ReserveOfferTest extends TestCase
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

    private function offer(int $remaining = 100, int $maxPerCustomer = 2): Offer
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

    public function test_it_reserves_and_decrements_stock(): void
    {
        $offer = $this->offer(remaining: 10);

        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 2);

        $this->assertSame(2, $reservation->qty);
        $this->assertSame(8, $offer->fresh()->remaining);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $reservation->pickup_code);
    }

    public function test_it_refuses_more_than_remaining(): void
    {
        $offer = $this->offer(remaining: 1, maxPerCustomer: 5);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer, $this->customer(), 3);
    }

    public function test_it_refuses_more_than_the_per_customer_cap(): void
    {
        $offer = $this->offer(remaining: 100, maxPerCustomer: 2);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer, $this->customer(), 3);
    }

    public function test_it_marks_the_offer_sold_out_at_zero(): void
    {
        $offer = $this->offer(remaining: 2, maxPerCustomer: 2);

        (new ReserveOffer)->handle($offer, $this->customer(), 2);

        $this->assertSame(0, $offer->fresh()->remaining);
        $this->assertSame(OfferStatus::SoldOut, $offer->fresh()->status);
    }

    public function test_it_rejects_a_non_positive_qty(): void
    {
        $offer = $this->offer();
        $user = $this->customer();

        foreach ([0, -1] as $badQty) {
            try {
                (new ReserveOffer)->handle($offer, $user, $badQty);
                $this->fail("Expected InvalidArgumentException for qty={$badQty}");
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_collecting_a_reservation_does_not_free_the_cap(): void
    {
        $offer = $this->offer(remaining: 100, maxPerCustomer: 2);
        $user = $this->customer();

        $reservation = (new ReserveOffer)->handle($offer, $user, 2);
        $reservation->update(['status' => ReservationStatus::Collected]);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer, $user, 1);
    }

    public function test_it_refuses_a_blocked_user(): void
    {
        $offer = $this->offer();
        $user = $this->customer();
        $user->update(['blocked_until' => now()->addDays(3)]);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer, $user, 1);
    }

    public function test_it_refuses_a_closed_offer(): void
    {
        $offer = $this->offer();
        $offer->update(['status' => OfferStatus::Closed]);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer->fresh(), $this->customer(), 1);
    }

    public function test_it_refuses_an_offer_whose_pickup_window_has_passed(): void
    {
        $offer = $this->offer();
        $offer->update(['pickup_end' => now()->subMinute()]);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer->fresh(), $this->customer(), 1);
    }

    // This exercises the stock-race guard sequentially, not concurrently:
    // PHP cannot reproduce true concurrent requests in-process, so three
    // reservations are simply made one after another until stock runs out.
    // The actual correctness argument against overselling under real
    // concurrency is the conditional `UPDATE ... WHERE remaining >= :qty`
    // in ReserveOffer::handle — a single atomic statement whose affected-row
    // count tells us whether we won the race, not this test.
    public function test_it_never_oversells_when_stock_runs_out(): void
    {
        $offer = $this->offer(remaining: 3, maxPerCustomer: 2);

        $succeeded = 0;

        foreach (['a', 'b', 'c'] as $letter) {
            try {
                (new ReserveOffer)->handle($offer, $this->customer("{$letter}@example.test"), 2);
                $succeeded++;
            } catch (ReservationFailed) {
                // expected once stock runs out
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(1, $offer->fresh()->remaining);
    }
}
