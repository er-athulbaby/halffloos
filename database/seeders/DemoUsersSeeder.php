<?php

namespace Database\Seeders;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
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

        $this->user('fatima@halffloos.test', 'Fatima', UserRole::Customer, '36123456');
        $this->user('admin@halffloos.test', 'Halffloos Admin', UserRole::Admin);

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
