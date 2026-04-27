<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'full_name' => 'Amjad Admin',
            'email'     => 'admin@musanada.com',
            'password'  => Hash::make('admin123456'),
            'role'      => 'admin',
            'is_active' => true,
        ]);
    }
}