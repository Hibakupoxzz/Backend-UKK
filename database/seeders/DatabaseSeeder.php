<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Akun demo disamakan dengan daftar akun di halaman login frontend.
        $demoUsers = [
            [
                'name' => 'Deva Fauzan Nuraqli',
                'email' => 'admin@eduabsen.com',
                'department' => 'IT',
                'role' => 'admin',
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'finance@eduabsen.com',
                'department' => 'Finance',
                'role' => 'finance',
            ],
            [
                'name' => 'Dewi Lestari',
                'email' => 'hrd@eduabsen.com',
                'department' => 'HRD',
                'role' => 'hrd',
            ],
            [
                'name' => 'Andi Saputra',
                'email' => 'pegawai@eduabsen.com',
                'department' => 'Marketing',
                'role' => 'pegawai',
            ],
            [
                'name' => 'Atasan Demo',
                'email' => 'atasan@eduabsen.com',
                'department' => 'Manajemen',
                'role' => 'atasan',
            ],
        ];

        foreach ($demoUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password123',   // otomatis di-hash oleh cast 'hashed'
                    'department' => $data['department'],
                ],
            );

            $user->syncRoles([$data['role']]);
        }

        // Hubungkan pegawai demo ke atasan demo.
        $atasan = User::where('email', 'atasan@eduabsen.com')->first();
        $pegawai = User::where('email', 'pegawai@eduabsen.com')->first();

        if ($atasan && $pegawai) {
            $pegawai->update(['atasan_id' => $atasan->id]);
        }
    }
}
