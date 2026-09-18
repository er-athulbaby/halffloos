<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(): Store
    {
        $user = User::create([
            'name' => 'Cold Store Owner',
            'email' => 'owner@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        return Store::create([
            'user_id' => $user->id,
            'name' => 'Hidd Cold Store',
            'area' => 'Hidd',
            'lat' => 26.1550,
            'lng' => 50.6550,
            'phone' => '17000000',
            'cr_number' => '12345-1',
            'food_licence_no' => 'MOH-9988',
            'status' => StoreStatus::Approved,
        ]);
    }

    private function makeOffer(Store $store): Offer
    {
        return Offer::create([
            'store_id' => $store->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => 100,
            'remaining' => 100,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->setTime(20, 0),
            'pickup_end' => now()->setTime(22, 0),
            'status' => OfferStatus::Active,
        ]);
    }

    public function test_an_offer_casts_money_to_fils(): void
    {
        $offer = $this->makeOffer($this->makeStore());

        $this->assertSame(500, $offer->fresh()->price_fils->fils());
        $this->assertSame('BHD 0.500', $offer->fresh()->price_fils->format());
    }

    public function test_the_database_rejects_a_discount_under_fifty_percent(): void
    {
        $this->expectException(QueryException::class);

        Offer::create([
            'store_id' => $this->makeStore()->id,
            'type' => OfferType::Item,
            'title' => 'Barely discounted milk',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('1.500'),
            'quantity' => 10,
            'remaining' => 10,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->setTime(20, 0),
            'pickup_end' => now()->setTime(22, 0),
            'status' => OfferStatus::Active,
        ]);
    }

    // The boundary itself: 50% is a floor, not a threshold to clear. Every other
    // fixture is 75% off, so flipping either trigger to `>=` would leave the rest
    // of the suite green while rejecting every genuine half-price listing.
    public function test_the_database_accepts_a_discount_of_exactly_fifty_percent(): void
    {
        $offer = Offer::create([
            'store_id' => $this->makeStore()->id,
            'type' => OfferType::Item,
            'title' => 'Exactly half price milk',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('1.000'),
            'quantity' => 10,
            'remaining' => 10,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->setTime(20, 0),
            'pickup_end' => now()->setTime(22, 0),
            'status' => OfferStatus::Active,
        ]);

        $this->assertSame(1000, $offer->fresh()->price_fils->fils());
    }

    public function test_the_database_rejects_an_update_that_breaches_fifty_percent(): void
    {
        $offer = $this->makeOffer($this->makeStore());

        $this->expectException(QueryException::class);

        $offer->price_fils = Money::fromString('1.500');
        $offer->save();
    }

    public function test_a_store_has_offers(): void
    {
        $store = $this->makeStore();

        $this->assertSame(0, $store->offers()->count());
        $this->assertSame(StoreStatus::Approved, $store->status);
    }
}
