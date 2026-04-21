<?php
// database/seeders/DatabaseSeeder.php
namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $temporaryPasswordExpiresAt = now()->addHours(config('security.temporary_password.expires_hours'));

        $seededAccounts = [
            [
                'name' => 'Mary Jane Orpeza',
                'email' => env('OWNER_EMAIL', 'owner@localhost.test'),
                'role' => 'owner',
            ],
            [
                'name' => 'Miguel Orpeza',
                'email' => 'delivery@localhost.test',
                'role' => 'delivery',
            ],
            [
                'name' => 'Pedro Reyes',
                'email' => 'helper@localhost.test',
                'role' => 'helper',
            ],
        ];

        $temporaryCredentials = [];

        foreach ($seededAccounts as $account) {
            $temporaryPassword = Str::password(20);

            User::create([
                'name' => $account['name'],
                'email' => $account['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => $account['role'],
                'must_change_password' => true,
                'password_changed_at' => null,
                'temp_password_expires_at' => $temporaryPasswordExpiresAt,
            ]);

            $temporaryCredentials[] = [
                'role' => $account['role'],
                'email' => $account['email'],
                'temporary_password' => $temporaryPassword,
                'expires_at' => $temporaryPasswordExpiresAt->toDateTimeString(),
            ];
        }

        if (app()->environment('local')) {
            $this->command?->warn('Temporary credentials are shown once for local presentation. Copy them now and rotate them after first login.');
            $this->command?->warn('Do not store these plaintext passwords in logs, screenshots, or committed files.');
            $this->command?->table(['Role', 'Email', 'Temporary Password', 'Expires At'], $temporaryCredentials);
        } else {
            $this->command?->info('Seeded default users with temporary passwords (plaintext intentionally not displayed outside local environment).');
        }

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
