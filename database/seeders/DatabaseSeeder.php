<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPermission = Permission::firstOrCreate(['name' => 'admin']);

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo($adminPermission);

        $michael = User::firstOrCreate(
            ['email' => 'michael@zorbl.com'],
            [
                'name' => 'Michael Greer',
                'password' => Hash::make('Sunday#1'),
                'email_verified_at' => now(),
            ]
        );
        $michael->assignRole($adminRole);

        if (App()->environment('local')) {
            $this->call([
                RoadmapSeeder::class,
                ActivitySeeder::class,
            ]);
        }

        $this->call([
            WordListSeeder::class,
            ClueEntrySeeder::class,
        ]);
    }
}
