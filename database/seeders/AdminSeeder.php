<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        // Define the credentials for the default admin user
        $adminEmail = 'admin@example.com';
        $adminPassword = '2182Admin-'; // Use a strong password in production!

        // Check if the admin user already exists to prevent duplicates
        if (!Admin::where('email', $adminEmail)->exists()) {
            Admin::create([
                'first_name' => 'Site',
                'last_name' => 'Administrator',
                'email' => $adminEmail,
                'staff_id' => 'testing_admin',
                'permission_level' => 1,
                'phone_number' => '0906552714272',
                'location' => 'lagos',
                'profile_picture' => null,
                'password' => Hash::make($adminPassword),
            ]);

            $this->command->info('Admin User created successfully!');
            $this->command->info("Credentials: E-mail: $adminEmail | Password: $adminPassword");
        } else {
            $this->command->warn('Admin User already exists.');
        }
    }
}
