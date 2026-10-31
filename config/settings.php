<?php

return [

    'super_admin' => [
        'name' => env('SUPERADMIN_NAME', 'Super Admin'),
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@simpro.local'),
        'password' => env('SUPERADMIN_PASSWORD', 'ganti-password-ini'),
    ],

    // Jumlah baris per halaman pada tabel daftar.
    'per_page' => 15,

    'sse' => [
        'duration' => (int) env('SSE_DURATION', 20),
    ],

];
