<?php
// database/seeders/DatabaseSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\InventoryItem;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Create default users
        User::create([
            'name' => 'Mary Jane Orpeza',
            'email' => 'owner@migail.com',
            'password' => Hash::make('password'),
            'role' => 'owner'
        ]);

        User::create([
            'name' => 'Miguel Orpeza',
            'email' => 'delivery@migail.com',
            'password' => Hash::make('password'),
            'role' => 'delivery'
        ]);

        User::create([
            'name' => 'Pedro Reyes',
            'email' => 'helper@migail.com',
            'password' => Hash::make('password'),
            'role' => 'helper'
        ]);

        // Create default inventory items
        InventoryItem::create([
            'name' => 'Purified Water',
            'description' => 'Purified water ready for gallon filling',
            'quantity' => 40,
            'threshold' => 10,
            'type' => 'water'
        ]);

        InventoryItem::create([
            'name' => 'Gallon Containers',
            'description' => 'Empty gallon containers',
            'quantity' => 50,
            'threshold' => 10,
            'type' => 'container'
        ]);

        InventoryItem::create([
            'name' => 'Returned Empty Gallons',
            'description' => 'Empty gallons returned by customers',
            'quantity' => 30,
            'threshold' => 10,
            'type' => 'empty'
        ]);

        InventoryItem::create([
            'name' => 'Bottle Caps',
            'description' => 'Caps for water gallon',
            'quantity' => 100,
            'threshold' => 20,
            'type' => 'cap'
        ]);

        InventoryItem::create([
            'name' => 'Seals',
            'description' => 'Security seals for water containers',
            'quantity' => 100,
            'threshold' => 20,
            'type' => 'seal'
        ]);
    }
}
