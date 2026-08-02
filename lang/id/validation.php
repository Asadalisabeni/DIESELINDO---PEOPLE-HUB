<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'confirmed' => 'Konfirmasi :attribute tidak sesuai.',
    'array' => ':attribute harus berupa daftar.',
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'distinct' => ':attribute memiliki nilai duplikat.',
    'in' => ':attribute yang dipilih tidak valid.',
    'min' => ['string' => ':attribute minimal :min karakter.', 'array' => ':attribute minimal berisi :min item.'],
    'max' => ['string' => ':attribute maksimal :max karakter.'],
    'password' => [
        'letters' => ':attribute harus memiliki setidaknya satu huruf.',
        'mixed' => ':attribute harus memiliki huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus memiliki setidaknya satu angka.',
        'symbols' => ':attribute harus memiliki setidaknya satu simbol.',
        'uncompromised' => ':attribute pernah muncul dalam kebocoran data. Gunakan kata sandi lain.',
    ],
    'attributes' => [
        'name' => 'nama', 'email' => 'email', 'password' => 'kata sandi',
        'current_password' => 'kata sandi saat ini', 'roles' => 'peran', 'role' => 'peran',
    ],
];
