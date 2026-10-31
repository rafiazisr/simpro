<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            StatusSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Data contoh hanya untuk lingkungan lokal.
        if (app()->environment('local')) {
            $this->call([
                DemoUserSeeder::class,
                DemoProjectSeeder::class,
            ]);
        }
    }
}
