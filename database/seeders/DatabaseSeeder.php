<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::create([
            'name' => 'Admin EduAbsen',
            'email' => 'admin@eduabsen.test',
            'password' => 'password',   // otomatis di-hash oleh cast 'hashed'
            'department' => 'IT',
        ]);
        $admin->assignRole('admin');

        $atasan = User::create([
            'name' => 'Budi Atasan',
            'email' => 'atasan@eduabsen.test',
            'password' => 'password',
            'department' => 'Operasional',
        ]);
        $atasan->assignRole('atasan');

        $pegawai = User::create([
            'name' => 'Sinta Pegawai',
            'email' => 'pegawai@eduabsen.test',
            'password' => 'password',
            'department' => 'Operasional',
            'atasan_id' => $atasan->id,
        ]);
        $pegawai->assignRole('pegawai');
    }
}
