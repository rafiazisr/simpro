<?php

return [

    /*
    | Akun Super Admin bawaan (dibuat oleh SuperAdminSeeder).
    | Ganti nilainya di file .env sebelum menjalankan seeder di server produksi.
    */
    'super_admin' => [
        'name' => env('SUPERADMIN_NAME', 'Super Admin'),
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@simpro.local'),
        'password' => env('SUPERADMIN_PASSWORD', 'ganti-password-ini'),
    ],

    // Jumlah baris per halaman pada tabel daftar.
    'per_page' => 15,

    /*
    | Server-Sent Events notifikasi: berapa detik satu koneksi ditahan sebelum
    | browser menyambung ulang. Diabaikan (0) saat memakai `php artisan serve`.
    */
    'sse' => [
        'duration' => (int) env('SSE_DURATION', 20),
    ],

];
