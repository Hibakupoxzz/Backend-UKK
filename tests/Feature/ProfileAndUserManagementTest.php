<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileAndUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $attributes = []): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeUser('pegawai', ['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Akun ini dinonaktifkan. Hubungi administrator.');
    }

    public function test_user_can_update_own_profile_and_password(): void
    {
        $user = $this->makeUser('pegawai');

        Sanctum::actingAs($user);

        $this->putJson('/api/me', [
            'name' => 'Nama Baru',
            'email' => 'nama.baru@example.com',
            'no_hp' => '081299998888',
            'jabatan' => 'Staf Operasional',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.email', 'nama.baru@example.com')
            ->assertJsonPath('data.no_hp', '081299998888')
            ->assertJsonPath('data.jabatan', 'Staf Operasional');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Baru',
            'no_hp' => '081299998888',
            'jabatan' => 'Staf Operasional',
        ]);

        $this->putJson('/api/me/password', [
            'current_password' => 'salah',
            'password' => 'passwordbaru',
            'password_confirmation' => 'passwordbaru',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'passwordbaru',
            'password_confirmation' => 'passwordbaru',
        ])->assertOk();

        $this->assertTrue(Hash::check('passwordbaru', $user->fresh()->password));

        // Token yang sedang dipakai tetap valid setelah ganti kata sandi.
        $this->getJson('/api/me')->assertOk();
    }

    public function test_user_can_upload_and_remove_profile_photo(): void
    {
        Storage::fake('public');

        $user = $this->makeUser('pegawai');

        Sanctum::actingAs($user);

        $response = $this->post('/api/me/foto', [
            'foto' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ])->assertOk();

        $this->assertNotNull($response->json('data.foto_url'));
        $this->assertNotNull($user->fresh()->foto_path);

        $this->deleteJson('/api/me/foto')
            ->assertOk()
            ->assertJsonPath('data.foto_url', null);

        $this->assertNull($user->fresh()->foto_path);
    }

    public function test_admin_can_manage_users_but_cannot_lock_themselves_out(): void
    {
        Role::findOrCreate('pegawai', 'web');
        Role::findOrCreate('atasan', 'web');

        $admin = $this->makeUser('admin');

        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/users', [
            'name' => 'Pegawai Baru',
            'email' => 'pegawai.baru@example.com',
            'password' => 'password123',
            'role' => 'pegawai',
            'department' => 'Operasional',
            'no_hp' => '081277776666',
            'jabatan' => 'Staf',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'pegawai')
            ->assertJsonPath('data.no_hp', '081277776666')
            ->assertJsonPath('data.is_active', true);

        $targetId = $created->json('data.id');
        $target = User::findOrFail($targetId);
        $target->createToken('test-device');

        $this->putJson("/api/users/{$targetId}", [
            'name' => 'Pegawai Diperbarui',
            'role' => 'atasan',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Pegawai Diperbarui')
            ->assertJsonPath('data.role', 'atasan')
            ->assertJsonPath('data.is_active', false);

        // Menonaktifkan akun mencabut seluruh token yang masih aktif.
        $this->assertSame(0, $target->tokens()->count());

        $this->deleteJson("/api/users/{$targetId}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $targetId]);

        $this->putJson("/api/users/{$admin->id}", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');

        $this->putJson("/api/users/{$admin->id}", ['role' => 'pegawai'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(422);
    }

    public function test_hrd_can_read_users_but_only_admin_can_write(): void
    {
        $hrd = $this->makeUser('hrd');
        $finance = $this->makeUser('finance');
        $pegawai = $this->makeUser('pegawai');

        Sanctum::actingAs($hrd);
        $this->getJson('/api/users')->assertOk();
        $this->postJson('/api/users', [
            'name' => 'Tidak Boleh',
            'email' => 'tidak.boleh@example.com',
            'password' => 'password123',
            'role' => 'pegawai',
        ])->assertForbidden();

        Sanctum::actingAs($finance);
        $this->getJson('/api/users')->assertForbidden();

        Sanctum::actingAs($pegawai);
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_user_with_trip_history_cannot_be_deleted(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('pegawai');

        Trip::create([
            'user_id' => $owner->id,
            'tujuan' => 'Bandung',
            'tanggal_berangkat' => now()->toDateString(),
            'tanggal_pulang' => now()->addDay()->toDateString(),
            'keperluan' => 'Kunjungan kerja',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$owner->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }
}
