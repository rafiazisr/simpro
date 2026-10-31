<?php

namespace Database\Seeders;

use App\Enums\UserLevel;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Andi Pratama', 'andi.pratama@simpro.local', UserLevel::Admin, true],
            ['Budi Santoso', 'budi.santoso@simpro.local', UserLevel::User, true],
            ['Citra Ayu', 'citra.ayu@simpro.local', UserLevel::User, true],
            ['Dewi Kartika', 'dewi.kartika@simpro.local', UserLevel::User, true],
            ['Fajar Nugroho', 'fajar.nugroho@simpro.local', UserLevel::User, false],
        ];

        foreach ($users as [$name, $email, $level, $active]) {
            User::query()->firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'user_level' => $level,
                'is_active' => $active,
                'email_verified_at' => now(),
            ]);
        }
    }
}
