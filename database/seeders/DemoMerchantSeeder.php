<?php

namespace Database\Seeders;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One approved merchant so the listing screen can be used before the admin
 * approval flow exists. Development only — never run in production.
 *
 * Approval is deliberately manual in this product: a store is only approved
 * after its CR number and Ministry of Health food licence have been checked.
 * This seeder short-circuits that gate, which is exactly why it must not ship.
 */
class DemoMerchantSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoMerchantSeeder must never run in production.');

            return;
        }

        $merchant = User::firstOrCreate(
            ['email' => 'merchant@halffloos.test'],
            [
                'name' => 'Ali Hassan',
                'password' => Hash::make('password'),
                'role' => UserRole::Merchant,
                'phone' => '17456789',
            ]
        );

        Store::firstOrCreate(
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

        $this->command->info('Demo merchant: merchant@halffloos.test / password');
    }
}
