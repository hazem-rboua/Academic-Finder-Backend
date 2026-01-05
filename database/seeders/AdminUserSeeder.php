<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default admin user
        User::updateOrCreate(
            ['email' => 'admin@academicfinder.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@academicfinder.com',
                'password' => Hash::make('Admin@123'),
                'user_type' => UserType::ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@academicfinder.com');
        $this->command->info('Password: Admin@123');
    }
}
