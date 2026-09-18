<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MerchantListStockTest extends TestCase
{
    use RefreshDatabase;

    private function merchant(StoreStatus $status = StoreStatus::Approved): User
    {
        $user = User::create([
            'name' => 'Ali Hassan',
            'email' => 'ali@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        Store::create([
            'user_id' => $user->id,
            'name' => 'Hidd Cold Store',
            'area' => 'Hidd',
            'lat' => 26.1550,
            'lng' => 50.6550,
            'phone' => '17456789',
            'cr_number' => '112233-1',
            'food_licence_no' => 'MOH-BH-9988',
            'status' => $status,
        ]);

        return $user;
    }

    private function form(mixed $c, string $price): mixed
    {
        return $c->set('title', 'Fresh milk 1L')
            ->set('retail_value', '2.000')
            ->set('price', $price)
            ->set('quantity', 100)
            ->set('expires_on', now()->addDay()->toDateString())
            ->set('pickup_end', '22:00');
    }

    public function test_a_merchant_can_list_stock(): void
    {
        $this->actingAs($this->merchant());

        $this->form(Livewire::test('merchant.list-stock'), '0.500')->call('save');

        $offer = Offer::sole();

        $this->assertSame('Fresh milk 1L', $offer->title);
        $this->assertSame(500, $offer->price_fils->fils());
        $this->assertSame(2000, $offer->retail_value_fils->fils());
        $this->assertSame(100, $offer->quantity);
        $this->assertSame(100, $offer->remaining);
    }

    public function test_it_rejects_a_discount_under_fifty_percent(): void
    {
        $this->actingAs($this->merchant());

        $this->form(Livewire::test('merchant.list-stock'), '1.500')
            ->call('save')
            ->assertHasErrors('price');

        $this->assertSame(0, Offer::count());
    }

    public function test_it_accepts_exactly_fifty_percent_off(): void
    {
        $this->actingAs($this->merchant());

        $this->form(Livewire::test('merchant.list-stock'), '1.000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1000, Offer::sole()->price_fils->fils());
    }

    public function test_a_merchant_whose_store_is_not_approved_is_refused(): void
    {
        $this->actingAs($this->merchant(StoreStatus::Pending));

        $this->get(route('merchant.stock'))->assertForbidden();
    }

    public function test_the_listing_screen_requires_a_login(): void
    {
        $this->get(route('merchant.stock'))->assertRedirect(route('login'));
    }
}
