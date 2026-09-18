<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminShopsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Halffloos Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Admin,
        ]);
    }

    private function applicantShop(StoreStatus $status = StoreStatus::Pending): Store
    {
        $merchant = User::create([
            'name' => 'Mariam',
            'email' => 'mariam'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        return Store::create([
            'user_id' => $merchant->id,
            'name' => 'Seef Fresh Market',
            'area' => 'Seef',
            'lat' => 26.2360,
            'lng' => 50.5440,
            'phone' => '17889900',
            'cr_number' => '778899-1',
            'food_licence_no' => 'MOH-BH-7788',
            'status' => $status,
        ]);
    }

    public function test_an_admin_sees_a_pending_shop_with_its_licence_numbers(): void
    {
        $this->applicantShop();
        $this->actingAs($this->admin());

        Livewire::test('admin.shops')
            ->assertSee('Seef Fresh Market')
            ->assertSee('778899-1')
            ->assertSee('MOH-BH-7788');
    }

    public function test_approving_records_who_verified_it_and_when(): void
    {
        $shop = $this->applicantShop();
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test('admin.shops')->call('approve', $shop->id);

        $shop->refresh();

        $this->assertSame(StoreStatus::Approved, $shop->status);
        $this->assertSame($admin->id, $shop->verified_by);
        $this->assertNotNull($shop->verified_at);
    }

    public function test_suspending_stops_a_shop_listing(): void
    {
        $shop = $this->applicantShop(StoreStatus::Approved);
        $this->actingAs($this->admin());

        Livewire::test('admin.shops')->call('suspend', $shop->id);

        $this->assertSame(StoreStatus::Suspended, $shop->fresh()->status);
    }

    /** Approval is the gate the liability story rests on — only admins pass it. */
    public function test_a_merchant_cannot_reach_the_approval_screen(): void
    {
        $shop = $this->applicantShop();
        $this->actingAs($shop->user);

        $this->get(route('admin.shops'))->assertForbidden();
    }

    public function test_a_customer_cannot_reach_the_approval_screen(): void
    {
        $customer = User::create([
            'name' => 'Fatima',
            'email' => 'fatima@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);

        $this->actingAs($customer);

        $this->get(route('admin.shops'))->assertForbidden();
    }

    public function test_the_approval_screen_requires_a_login(): void
    {
        $this->get(route('admin.shops'))->assertRedirect(route('login'));
    }
}
