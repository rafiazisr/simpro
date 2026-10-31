<?php

namespace Database\Seeders;

use App\Enums\UserLevel;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('settings.super_admin');

        $user = User::withTrashed()->firstOrNew(['email' => $cfg['email']]);

        if (! $user->exists) {
            $user->password = $cfg['password'];
        }

        $user->fill([
            'name' => $cfg['name'],
            'user_level' => UserLevel::SuperAdmin,
            'is_active' => true,
        ]);
        $user->email_verified_at ??= now();
        $user->deleted_at = null;
        $user->save();

        $this->command?->info("Super Admin siap: {$cfg['email']}");
    }
}
