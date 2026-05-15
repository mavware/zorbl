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
                'password' => Hash::make(config('app.admin_password')),
                'email_verified_at' => now(),
            ]
        );
        $michael->assignRole($adminRole);

        $this->call([
            RoadmapSeeder::class,
            TagSeeder::class,
            TemplateSeeder::class,
            TemplateTagSeeder::class,
            HelpArticleSeeder::class,
            WordListSeeder::class,
            //            ClueEntrySeeder::class,
            ActivitySeeder::class,
        ]);
        // Call php artisan setup:platform to seed the rest of the data
    }
}
