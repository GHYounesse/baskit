<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed a known admin, a known customer, and some random customers.
     * Safe to run repeatedly: the known accounts are upserted by email.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $accounts = [
            ['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true],
            ['name' => 'Demo Customer', 'email' => 'customer@example.com', 'is_admin' => false],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                $account + ['password' => $password, 'email_verified_at' => now()],
            );
        }

        if (User::where('is_admin', false)->count() < 10) {
            User::factory()->count(10)->create(['is_admin' => false]);
        }
    }
}
