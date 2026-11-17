<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Super Admin (Sarah Connor)
        $superAdmin = User::create([
            'name' => 'Sarah Connor (Admin)',
            'email' => 'admin@foodpoint.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        Role::create([
            'user_id' => $superAdmin->id,
            'admin' => true,
        ]);

        // 2. Restaurant Owner (John Connor)
        $owner = User::create([
            'name' => 'John Connor (Owner)',
            'email' => 'owner@foodpoint.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);
        Role::create([
            'user_id' => $owner->id,
            'owner' => true,
        ]);

        // 3. Restaurant Staff (Kyle Reese)
        $staff = User::create([
            'name' => 'Kyle Reese (Staff)',
            'email' => 'staff@foodpoint.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);
        Role::create([
            'user_id' => $staff->id,
            'staff' => true,
        ]);

        // 4. Customer (John Doe)
        $customer = User::create([
            'name' => 'John Doe',
            'email' => 'user@foodpoint.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
        ]);
        Role::create([
            'user_id' => $customer->id,
            'customer' => true,
        ]);

        // 5. Delivery Rider (Marcus Wright)
        $rider = User::create([
            'name' => 'Marcus Wright (Rider)',
            'email' => 'rider@foodpoint.com',
            'password' => Hash::make('password'),
            'role' => 'rider',
        ]);
        Role::create([
            'user_id' => $rider->id,
            'rider' => true,
        ]);
    }
}
