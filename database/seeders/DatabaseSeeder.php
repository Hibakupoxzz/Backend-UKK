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
                'jabatan' => 'Administrator Sistem',
                'no_hp' => '081200000001',
                'role' => 'admin',
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'finance@eduabsen.com',
                'department' => 'Finance',
                'jabatan' => 'Staf Keuangan',
                'no_hp' => '081200000002',
                'role' => 'finance',
            ],
            [
                'name' => 'Dewi Lestari',
                'email' => 'hrd@eduabsen.com',
                'department' => 'HRD',
                'jabatan' => 'Staf HRD',
                'no_hp' => '081200000003',
                'role' => 'hrd',
            ],
            [
                'name' => 'Andi Saputra',
                'email' => 'pegawai@eduabsen.com',
                'department' => 'Marketing',
                'jabatan' => 'Staf Marketing',
                'no_hp' => '081200000004',
                'role' => 'pegawai',
            ],
            [
                'name' => 'Atasan Demo',
                'email' => 'atasan@eduabsen.com',
                'department' => 'Manajemen',
                'jabatan' => 'Manajer Operasional',
                'no_hp' => '081200000005',
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
                    'jabatan' => $data['jabatan'],
                    'no_hp' => $data['no_hp'],
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
