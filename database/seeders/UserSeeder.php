<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'id' => 1,
                'name' => 'Frizam Pembeli (Demo)',
                'email' => 'pembeli@medcom.com',
                'password' => Hash::make('password'),
                'phone' => '081987654321',
                'gender' => 'male',
                'email_verified_at' => now(),
                'is_verified_seller' => false,
            ],
            [
                'id' => 2,
                'name' => 'Garasi SpareHub Penjual',
                'email' => 'penjual@medcom.com',
                'password' => Hash::make('password'),
                'phone' => '081234567890',
                'gender' => 'male',
                'email_verified_at' => now(),
                'is_verified_seller' => true,
            ],
            [
                'id' => 3,
                'name' => 'Admin Medcom DevOps',
                'email' => 'admin@medcom.com',
                'password' => Hash::make('password'),
                'phone' => '081122334455',
                'gender' => 'male',
                'email_verified_at' => now(),
                'is_verified_seller' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
