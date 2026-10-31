<?php

// Pesan validasi Bahasa Indonesia (subset yang dipakai aplikasi).
return [
    'array' => ':Attribute harus berupa daftar.',
    'boolean' => ':Attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak sama.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'enum' => ':Attribute yang dipilih tidak valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'max' => [
        'numeric' => ':Attribute maksimal :max.',
        'file' => ':Attribute maksimal :max kilobyte.',
        'string' => ':Attribute maksimal :max karakter.',
        'array' => ':Attribute maksimal berisi :max item.',
    ],
    'min' => [
        'numeric' => ':Attribute minimal :min.',
        'file' => ':Attribute minimal :min kilobyte.',
        'string' => ':Attribute minimal :min karakter.',
        'array' => ':Attribute minimal berisi :min item.',
    ],
    'password' => [
        'letters' => ':Attribute harus mengandung minimal satu huruf.',
        'mixed' => ':Attribute harus mengandung huruf besar dan huruf kecil.',
        'numbers' => ':Attribute harus mengandung minimal satu angka.',
        'symbols' => ':Attribute harus mengandung minimal satu simbol.',
        'uncompromised' => ':Attribute ini pernah bocor di internet. Gunakan :attribute lain.',
    ],
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah digunakan.',

    'attributes' => [
        'name' => 'nama',
        'email' => 'email',
        'password' => 'password',
    ],
];
