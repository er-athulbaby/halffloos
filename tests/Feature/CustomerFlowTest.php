<?php

namespace Tests\Feature;

use App\Enums\OfferCategory;
use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\ReservationStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerFlowTest extends TestCase
{
    use RefreshDatabase;

    private function store(StoreStatus $status = StoreStatus::Approved): Store
    {
        $merchant = User::create([
            'name' => 'Ali Hassan',
            'email' => 'ali'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        return Store::create([
            'user_id' => $merchant->id,
            'name' => 'Hidd Cold Store',
            'area' => 'Hidd',
            'lat' => 26.1550,
            'lng' => 50.6550,
            'phone' => '17456789',
            'cr_number' => '112233-'.uniqid(),
            'food_licence_no' => 'MOH-BH-9988',
            'status' => $status,
        ]);
    }

    private function offer(Store $store, array $overrides = []): Offer
    {
        return Offer::create(array_merge([
            'store_id' => $store->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => 100,
            'remaining' => 100,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->subHour(),
            'pickup_end' => now()->addHours(2),
            'status' => OfferStatus::Active,
        ], $overrides));
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Fatima',
            'email' => 'fatima@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);
    }

    public function test_registration_creates_a_customer(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Fatima',
            'email' => 'fatima@example.bh',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('browse'));

        $this->assertSame(UserRole::Customer, User::sole()->role);
    }

    /**
     * The role is hard-coded in the controller, never taken from input.
     * A merchant account is the thing the CR and food-licence check gates.
     */
    public function test_registration_cannot_be_used_to_create_a_merchant(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Attacker',
            'email' => 'attacker@example.bh',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'role' => 'merchant',
        ]);

        $this->assertSame(UserRole::Customer, User::sole()->role);
    }

    public function test_browse_shows_live_offers_from_approved_shops(): void
    {
        $this->offer($this->store());
        $this->actingAs($this->customer());

        Livewire::test('customer.browse')->assertSee('Fresh milk 1L');
    }

    public function test_browse_hides_offers_from_shops_that_are_not_approved(): void
    {
        $this->offer($this->store(StoreStatus::Pending));
        $this->actingAs($this->customer());

        Livewire::test('customer.browse')->assertDontSee('Fresh milk 1L');
    }

    public function test_browse_hides_offers_whose_window_has_closed(): void
    {
        $this->offer($this->store(), [
            'pickup_start' => now()->subHours(4),
            'pickup_end' => now()->subHour(),
        ]);
        $this->actingAs($this->customer());

        Livewire::test('customer.browse')->assertDontSee('Fresh milk 1L');
    }

    public function test_a_customer_can_reserve_and_is_shown_a_code(): void
    {
        $offer = $this->offer($this->store());
        $this->actingAs($this->customer());

        $component = Livewire::test('customer.browse')->call('reserve', $offer->id, 1);

        $code = $component->get('reservedCode');

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $code);
        $this->assertSame(99, $offer->fresh()->remaining);
    }

    public function test_reserving_past_the_cap_shows_the_reason_not_a_crash(): void
    {
        $offer = $this->offer($this->store(), ['max_per_customer' => 1]);
        $this->actingAs($this->customer());

        Livewire::test('customer.browse')
            ->call('reserve', $offer->id, 1)
            ->call('reserve', $offer->id, 1)
            ->assertSet('reservedCode', null)
            ->assertSee('You can reserve at most 1');
    }

    public function test_browse_requires_a_login(): void
    {
        $this->get(route('browse'))->assertRedirect(route('login'));
    }

    public function test_a_category_chip_filters_the_list(): void
    {
        $store = $this->store();
        $this->offer($store, ['title' => 'Sourdough loaf', 'category' => OfferCategory::Bakery]);
        $this->offer($store, ['title' => 'Fresh milk 1L', 'category' => OfferCategory::Dairy]);
        $this->actingAs($this->customer());

        Livewire::test('customer.browse')
            ->call('filterBy', 'bakery')
            ->assertSee('Sourdough loaf')
            ->assertDontSee('Fresh milk 1L');
    }

    public function test_tapping_the_same_chip_again_clears_the_filter(): void
    {
        $store = $this->store();
        $this->offer($store, ['title' => 'Fresh milk 1L', 'category' => OfferCategory::Dairy]);
        $this->actingAs($this->customer());

        Livewire::test('customer.browse')
            ->call('filterBy', 'bakery')
            ->assertDontSee('Fresh milk 1L')
            ->call('filterBy', 'bakery')
            ->assertSet('category', null)
            ->assertSee('Fresh milk 1L');
    }

    public function test_search_matches_the_item_name(): void
    {
        $store = $this->store();
        $this->offer($store, ['title' => 'Sourdough loaf', 'category' => OfferCategory::Bakery]);
        $this->offer($store, ['title' => 'Fresh milk 1L', 'category' => OfferCategory::Dairy]);
        $this->actingAs($this->customer());

        Livewire::test('customer.browse')
            ->set('search', 'sourdough')
            ->assertSee('Sourdough loaf')
            ->assertDontSee('Fresh milk 1L');
    }

    public function test_search_matches_the_shop_name(): void
    {
        $this->offer($this->store(), ['title' => 'Fresh milk 1L']);
        $this->actingAs($this->customer());

        Livewire::test('customer.browse')
            ->set('search', 'Hidd Cold')
            ->assertSee('Fresh milk 1L');
    }

    public function test_a_merchant_collects_by_code_and_sees_what_to_hand_over(): void
    {
        $store = $this->store();
        $offer = $this->offer($store);
        $this->actingAs($this->customer());

        $code = Livewire::test('customer.browse')
            ->call('reserve', $offer->id, 2)
            ->get('reservedCode');

        $this->actingAs($store->user);

        Livewire::test('merchant.till')
            ->set('code', strtolower($code))
            ->call('collect')
            ->assertSee('Fresh milk 1L')
            ->assertSee('BHD 1.000')
            ->assertSet('code', '');

        $this->assertSame(
            ReservationStatus::Collected,
            $offer->reservations()->sole()->status
        );
    }

    public function test_my_codes_shows_a_live_pickup_code(): void
    {
        $offer = $this->offer($this->store());
        $this->actingAs($this->customer());

        $code = Livewire::test('customer.browse')
            ->call('reserve', $offer->id, 1)
            ->get('reservedCode');

        Livewire::test('customer.reservations')
            ->assertSee($code)
            ->assertSee('Fresh milk 1L');
    }

    public function test_my_codes_never_shows_someone_elses_reservation(): void
    {
        $offer = $this->offer($this->store());
        $this->actingAs($this->customer());

        $code = Livewire::test('customer.browse')
            ->call('reserve', $offer->id, 1)
            ->get('reservedCode');

        $other = User::create([
            'name' => 'Omar',
            'email' => 'omar@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);
        $this->actingAs($other);

        Livewire::test('customer.reservations')->assertDontSee($code);
    }

    public function test_cancelling_from_my_codes_returns_the_stock(): void
    {
        $offer = $this->offer($this->store());
        $customer = $this->customer();
        $this->actingAs($customer);

        Livewire::test('customer.browse')->call('reserve', $offer->id, 2);
        $this->assertSame(98, $offer->fresh()->remaining);

        $reservation = $customer->reservations()->sole();

        Livewire::test('customer.reservations')
            ->call('cancel', $reservation->id)
            ->assertSee('Cancelled');

        $this->assertSame(100, $offer->fresh()->remaining);
        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
    }

    public function test_my_codes_is_empty_before_reserving_anything(): void
    {
        $this->actingAs($this->customer());

        Livewire::test('customer.reservations')->assertSee('No codes yet');
    }

    public function test_an_unknown_code_at_the_till_reports_the_reason(): void
    {
        $store = $this->store();
        $this->actingAs($store->user);

        Livewire::test('merchant.till')
            ->set('code', 'ZZZZZZ')
            ->call('collect')
            ->assertSee('No reservation found')
            ->assertSet('collected', null);
    }
}
