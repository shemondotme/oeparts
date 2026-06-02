<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@oeparts.test',
            'password' => Hash::make('Admin@123456'),
            'is_active' => true,
        ]);

        $admin->assignRole('super_admin');
    }
}
