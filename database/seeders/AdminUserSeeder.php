<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@tutorreservation.test',
            ],
            [
                'first_names' => 'Admin',
                'last_names' => 'Principal',
                'password' => Hash::make('Admin12345'),
                'role_id' => 1,
            ]
        );
    }
}
