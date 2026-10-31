<?php

namespace Database\Seeders;

use App\Enums\UserLevel;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('settings.super_admin');

        if (app()->isProduction() && ($cfg['password'] === 'ganti-password-ini' || strlen((string) $cfg['password']) < 12)) {
            throw new RuntimeException('Isi SUPERADMIN_PASSWORD di .env (minimal 12 karakter, bukan nilai bawaan) sebelum menjalankan seeder di production.');
        }

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
