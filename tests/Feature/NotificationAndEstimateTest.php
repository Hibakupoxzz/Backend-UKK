<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationAndEstimateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeTrip(User $owner, array $overrides = []): Trip
    {
        return Trip::create([
            'user_id' => $owner->id,
            'tujuan' => 'Semarang',
            'tanggal_berangkat' => now()->toDateString(),
            'tanggal_pulang' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Kunjungan kerja',
            'status' => 'draft',
            'estimasi_biaya' => 500000,
            ...$overrides,
        ]);
    }

    public function test_trip_estimate_is_saved_and_variance_is_exposed(): void
    {
        $owner = $this->makeUser('pegawai');
        $trip = $this->makeTrip($owner);

        $trip->expenses()->create([
            'jenis' => 'transportasi',
            'jumlah' => 450000,
            'tanggal' => now()->toDateString(),
        ]);

        Sanctum::actingAs($owner);

        $detail = $this->getJson("/api/trips/{$trip->id}")->assertOk();
        $this->assertEquals(500000, $detail->json('data.estimasi_biaya'));
        $this->assertEquals(450000, $detail->json('data.total_biaya'));
        $this->assertEquals(-50000, $detail->json('data.selisih_biaya'));

        $this->putJson("/api/trips/{$trip->id}", [
            'tujuan' => 'Semarang',
            'tanggal_berangkat' => now()->toDateString(),
            'tanggal_pulang' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Kunjungan kerja',
            'estimasi_biaya' => 600000,
        ])->assertOk()->assertJsonPath('data.estimasi_biaya', 600000);
    }

    public function test_dashboard_and_trip_report_include_estimate_and_realization(): void
    {
        $owner = $this->makeUser('pegawai');
        $trip = $this->makeTrip($owner);

        $trip->expenses()->create([
            'jenis' => 'penginapan',
            'jumlah' => 550000,
            'tanggal' => now()->toDateString(),
        ]);

        Sanctum::actingAs($owner);

        $dashboard = $this->getJson('/api/dashboard')->assertOk();
        $this->assertEquals(500000, $dashboard->json('data.total_estimasi'));
        $this->assertEquals(50000, $dashboard->json('data.total_selisih'));
        $this->assertSame(1, $dashboard->json('data.perjalanan_dengan_estimasi'));

        $report = $this->getJson('/api/reports/trips')->assertOk();
        $row = $report->json('rows.0');

        $this->assertEquals(550000, $row[7]);
        $this->assertEquals(500000, $row[8]);
        $this->assertEquals(50000, $row[9]);
        $this->assertContains('Estimasi Biaya (Rp)', $report->json('headings'));
        $this->assertStringContainsString('Total estimasi', $report->json('footer'));
    }

    public function test_submitting_a_trip_creates_an_in_app_notification_for_the_supervisor(): void
    {
        $atasan = $this->makeUser('atasan');
        $owner = $this->makeUser('pegawai');
        $owner->update(['atasan_id' => $atasan->id]);

        $trip = $this->makeTrip($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/trips/{$trip->id}/submit")->assertOk();

        $notification = $atasan->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('trip_submitted', $notification->data['type']);
        $this->assertSame("/atasan/persetujuan/{$trip->id}", $notification->data['url']);
    }

    public function test_users_only_see_and_read_their_own_notifications(): void
    {
        $user = $this->makeUser('pegawai');
        $other = $this->makeUser('pegawai');

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\TripDecided',
            'data' => [
                'type' => 'trip_decided',
                'title' => 'Pengajuan disetujui',
                'message' => 'Perjalanan Anda disetujui.',
                'url' => '/pegawai/laporan',
            ],
        ]);

        $otherNotification = $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\TripDecided',
            'data' => [
                'type' => 'trip_decided',
                'title' => 'Notifikasi orang lain',
                'message' => 'Tidak boleh terlihat.',
                'url' => '/pegawai/laporan',
            ],
        ]);

        Sanctum::actingAs($user);

        $list = $this->getJson('/api/notifications')->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame(1, $list->json('unread_count'));

        $this->postJson("/api/notifications/{$otherNotification->id}/read")->assertNotFound();

        $this->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_all_notifications_can_be_marked_as_read(): void
    {
        $user = $this->makeUser('pegawai');

        foreach (range(1, 3) as $index) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\PaymentDecided',
                'data' => [
                    'type' => 'payment_decided',
                    'title' => "Notifikasi {$index}",
                    'message' => 'Pembayaran diproses.',
                    'url' => '/pegawai/laporan',
                ],
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 3);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
