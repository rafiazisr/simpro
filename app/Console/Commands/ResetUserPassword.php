<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password {email} {--password= : Password baru (tanpa opsi ini akan ditanyakan)}';

    protected $description = 'Ganti password user dari server, termasuk Super Admin';

    public function handle(): int
    {
        $user = User::withTrashed()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User dengan email itu tidak ditemukan.');

            return self::FAILURE;
        }

        $password = $this->option('password');

        if ($password === null) {
            $password = (string) $this->secret('Password baru');

            if ($password !== (string) $this->secret('Ulangi password baru')) {
                $this->error('Password tidak sama.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(['password' => $password], ['password' => ['required', Password::min(8)]]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password])->save();
        $user->terminateSessions();

        $this->info("Password {$user->email} sudah diganti. Sesi yang sedang login diputus.");

        return self::SUCCESS;
    }
}
