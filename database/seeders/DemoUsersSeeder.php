<?php

namespace Database\Seeders;

use App\Enums\OfferCategory;
use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One account per role, plus an approved shop, so the app can be demonstrated
 * before the admin approval flow exists. Development only.
 *
 * Approval is deliberately manual in this product: a shop is only approved once
 * its CR number and Ministry of Health food licence have been checked. This
 * seeder short-circuits that gate, which is exactly why it must not ship.
 */
class DemoUsersSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoUsersSeeder must never run in production.');

            return;
        }

        $merchant = $this->user('merchant@halffloos.test', 'Ali Hassan', UserRole::Merchant, '17456789');

        $store = Store::firstOrCreate(
            ['cr_number' => '112233-1'],
            [
                'user_id' => $merchant->id,
                'name' => 'Hidd Cold Store',
                'name_ar' => 'برادة الحد',
                'area' => 'Hidd',
                'lat' => 26.1550,
                'lng' => 50.6550,
                'phone' => '17456789',
                'pickup_instructions' => 'Ask at the counter for the app collection.',
                'food_licence_no' => 'MOH-BH-9988',
                'status' => StoreStatus::Approved,
                'verified_at' => now(),
            ]
        );

        $baker = $this->user('bakery@halffloos.test', 'Yusuf Rahman', UserRole::Merchant, '17223344');

        $bakery = Store::firstOrCreate(
            ['cr_number' => '445566-1'],
            [
                'user_id' => $baker->id,
                'name' => 'Adliya Bakery',
                'name_ar' => 'مخبز العدلية',
                'area' => 'Adliya',
                'lat' => 26.2100,
                'lng' => 50.5900,
                'phone' => '17223344',
                'food_licence_no' => 'MOH-BH-4455',
                'status' => StoreStatus::Approved,
                'verified_at' => now(),
            ]
        );

        // A shop still waiting on its CR and food-licence check, so the admin
        // approval queue is not empty in a demo.
        $applicant = $this->user('seef@halffloos.test', 'Mariam Al Khalifa', UserRole::Merchant, '17889900');

        Store::firstOrCreate(
            ['cr_number' => '778899-1'],
            [
                'user_id' => $applicant->id,
                'name' => 'Seef Fresh Market',
                'name_ar' => 'سوق السيف الطازج',
                'area' => 'Seef',
                'lat' => 26.2360,
                'lng' => 50.5440,
                'phone' => '17889900',
                'food_licence_no' => 'MOH-BH-7788',
                'status' => StoreStatus::Pending,
            ]
        );

        $this->user('fatima@halffloos.test', 'Fatima', UserRole::Customer, '36123456');
        $this->user('admin@halffloos.test', 'Halffloos Admin', UserRole::Admin);

        $this->offers($store, [
            ['Fresh milk 1L', OfferCategory::Dairy, '2.000', '0.500', 40],
            ['Greek yoghurt 500g', OfferCategory::Dairy, '1.200', '0.400', 18],
            ['Bananas 1kg', OfferCategory::Produce, '0.900', '0.300', 25],
            ['Orange juice 1L', OfferCategory::Drinks, '1.500', '0.600', 12],
            ['Basmati rice 2kg', OfferCategory::Pantry, '3.200', '1.500', 8],
        ]);

        $this->offers($bakery, [
            ['Croissants, box of 6', OfferCategory::Bakery, '2.400', '0.800', 10],
            ['Sourdough loaf', OfferCategory::Bakery, '1.800', '0.500', 6],
            ['Chicken sandwich', OfferCategory::Meals, '1.600', '0.700', 14],
        ]);
    }

    /** @param  array<int, array{0:string,1:OfferCategory,2:string,3:string,4:int}>  $rows */
    private function offers(Store $store, array $rows): void
    {
        foreach ($rows as [$title, $category, $retail, $price, $qty]) {
            Offer::firstOrCreate(
                ['store_id' => $store->id, 'title' => $title],
                [
                    'type' => OfferType::Item,
                    'category' => $category,
                    'retail_value_fils' => Money::fromString($retail),
                    'price_fils' => Money::fromString($price),
                    'quantity' => $qty,
                    'remaining' => $qty,
                    'max_per_customer' => 2,
                    'expires_on' => now()->addDay()->toDateString(),
                    'pickup_start' => now()->subHour(),
                    'pickup_end' => now()->addHours(4),
                    'status' => OfferStatus::Active,
                ]
            );
        }

        $this->command->newLine();
        $this->command->info('Demo accounts — password for all three is "'.self::PASSWORD.'"');
        $this->command->table(
            ['Role', 'Email', 'Lands on'],
            [
                ['Merchant', 'merchant@halffloos.test', '/stock and /till'],
                ['Customer', 'fatima@halffloos.test', '/browse'],
                ['Admin', 'admin@halffloos.test', '/browse — no admin screens exist yet'],
            ]
        );
    }

    private function user(string $email, string $name, UserRole $role, ?string $phone = null): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make(self::PASSWORD),
                'role' => $role,
            ]
        );
    }
}
