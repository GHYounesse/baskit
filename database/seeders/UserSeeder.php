<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Named customers, so reviews and orders in the demo show believable names.
     */
    private const CUSTOMERS = [
        'Maya Chen', 'Liam Okafor', 'Sofia Martins', 'Noah Fischer', 'Amara Singh',
        'Lucas Moreau', 'Hana Yamamoto', 'Omar Haddad', 'Elena Petrova', 'Jack Brennan',
        'Priya Nair', 'Mateo Alvarez',
    ];

    /**
     * Seed a known admin, a known customer and some named customers. Needs no
     * Faker. Safe to run repeatedly: every account is upserted by email.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $accounts = [
            ['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true],
            ['name' => 'Demo Customer', 'email' => 'customer@example.com', 'is_admin' => false],
        ];

        foreach (self::CUSTOMERS as $name) {
            $accounts[] = [
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                'is_admin' => false,
            ];
        }

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                $account + ['password' => $password, 'email_verified_at' => now()],
            );
        }
    }
}
