<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrgWideReadAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeTrip(User $owner): Trip
    {
        return Trip::create([
            'user_id' => $owner->id,
            'tujuan' => 'Bandung',
            'tanggal_berangkat' => now()->toDateString(),
            'tanggal_pulang' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Kunjungan kerja',
            'status' => 'berjalan',
        ]);
    }

    public function test_hrd_and_finance_can_read_org_wide_trips_but_cannot_write(): void
    {
        $owner = $this->makeUser('pegawai');
        $trip = $this->makeTrip($owner);

        foreach (['hrd', 'finance'] as $role) {
            $reader = $this->makeUser($role);

            Sanctum::actingAs($reader);

            $this->getJson('/api/trips')
                ->assertOk()
                ->assertJsonFragment(['id' => $trip->id]);

            $this->getJson("/api/trips/{$trip->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $trip->id);

            $this->getJson("/api/trips/{$trip->id}/expenses")->assertOk();
            $this->getJson('/api/dashboard')->assertOk();
            $this->getJson('/api/reports/trips')->assertOk();
            $this->getJson('/api/reports/expenses')->assertOk();

            // Baca org-wide tidak memberi hak menulis.
            $this->postJson("/api/trips/{$trip->id}/approve")->assertForbidden();
            $this->postJson("/api/trips/{$trip->id}/expenses", [
                'jenis' => 'konsumsi',
                'jumlah' => 1000,
                'tanggal' => now()->toDateString(),
            ])->assertForbidden();
        }
    }

    public function test_only_admin_and_hrd_can_list_users(): void
    {
        Role::findOrCreate('pegawai', 'web');
        Role::findOrCreate('hrd', 'web');
        Role::findOrCreate('finance', 'web');

        $owner = User::factory()->create();
        $owner->assignRole('pegawai');

        $hrd = User::factory()->create();
        $hrd->assignRole('hrd');

        $finance = User::factory()->create();
        $finance->assignRole('finance');

        Sanctum::actingAs($hrd);
        $this->getJson('/api/users')->assertOk();

        Sanctum::actingAs($finance);
        $this->getJson('/api/users')->assertForbidden();
    }
}
