<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeTrip(User $owner, string $status = 'selesai', float $biaya = 250000): Trip
    {
        $trip = Trip::create([
            'user_id' => $owner->id,
            'tujuan' => 'Surabaya',
            'tanggal_berangkat' => now()->subDays(3)->toDateString(),
            'tanggal_pulang' => now()->subDay()->toDateString(),
            'keperluan' => 'Rapat koordinasi',
            'status' => $status,
        ]);

        if ($biaya > 0) {
            $trip->expenses()->create([
                'jenis' => 'transportasi',
                'jumlah' => $biaya,
                'tanggal' => now()->subDays(2)->toDateString(),
            ]);
        }

        return $trip;
    }

    public function test_owner_can_request_payment_and_finance_can_verify_and_pay(): void
    {
        Notification::fake();

        $owner = $this->makeUser('pegawai');
        $finance = $this->makeUser('finance');
        $trip = $this->makeTrip($owner);

        Sanctum::actingAs($owner);

        $created = $this->postJson("/api/trips/{$trip->id}/payments", [
            'catatan' => 'Mohon segera diproses.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.jumlah_diajukan', 250000);

        $paymentId = $created->json('data.id');

        Sanctum::actingAs($finance);

        $this->postJson("/api/payments/{$paymentId}/verify", [
            'jumlah_disetujui' => 200000,
            'catatan' => 'Biaya taksi tidak sesuai standar.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'disetujui')
            ->assertJsonPath('data.jumlah_disetujui', 200000);

        $this->postJson("/api/payments/{$paymentId}/pay", [
            'metode' => 'transfer',
            'no_referensi' => 'TRX-001',
        ])->assertOk()
            ->assertJsonPath('data.status', 'dibayar')
            ->assertJsonPath('data.no_referensi', 'TRX-001');

        $this->assertNotNull(
            $this->getJson("/api/payments/{$paymentId}")->json('data.paid_at')
        );
    }

    public function test_payment_cannot_be_requested_before_trip_finishes_or_above_total(): void
    {
        $owner = $this->makeUser('pegawai');
        $berjalan = $this->makeTrip($owner, 'berjalan');
        $selesai = $this->makeTrip($owner, 'selesai', 100000);

        Sanctum::actingAs($owner);

        $this->postJson("/api/trips/{$berjalan->id}/payments")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->postJson("/api/trips/{$selesai->id}/payments", ['jumlah_diajukan' => 150000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('jumlah_diajukan');
    }

    public function test_trip_cannot_have_two_active_payments_and_owner_only_sees_own_payments(): void
    {
        Notification::fake();

        $owner = $this->makeUser('pegawai');
        $other = $this->makeUser('pegawai');
        $trip = $this->makeTrip($owner);
        $otherTrip = $this->makeTrip($other);

        Sanctum::actingAs($owner);
        $this->postJson("/api/trips/{$trip->id}/payments")->assertCreated();
        $this->postJson("/api/trips/{$trip->id}/payments")
            ->assertStatus(422)
            ->assertJsonValidationErrors('trip');

        $payments = $this->getJson('/api/payments')->assertOk()->json('data');
        $this->assertCount(1, $payments);
        $this->assertSame($trip->id, $payments[0]['trip_id']);

        // Pemilik lain tidak boleh melihat maupun mengajukan pembayaran untuk trip ini.
        Sanctum::actingAs($other);
        $this->getJson("/api/payments/{$payments[0]['id']}")->assertForbidden();
        $this->postJson("/api/trips/{$trip->id}/payments")->assertForbidden();
        $this->assertSame([], $this->getJson('/api/payments')->json('data'));

        $this->assertDatabaseCount('payments', 1);
        $this->assertNotNull($otherTrip);
    }

    public function test_only_admin_and_finance_can_verify_payments(): void
    {
        Notification::fake();

        $owner = $this->makeUser('pegawai');
        $hrd = $this->makeUser('hrd');
        $trip = $this->makeTrip($owner);

        Sanctum::actingAs($owner);
        $paymentId = $this->postJson("/api/trips/{$trip->id}/payments")->json('data.id');

        Sanctum::actingAs($hrd);
        $this->postJson("/api/payments/{$paymentId}/verify")->assertForbidden();
        $this->postJson("/api/payments/{$paymentId}/pay", [
            'metode' => 'tunai',
        ])->assertForbidden();

        // HRD tetap boleh membaca sebagai pembaca org-wide.
        $this->getJson("/api/payments/{$paymentId}")->assertOk();
    }

    public function test_rejected_payment_cannot_be_paid(): void
    {
        Notification::fake();

        $owner = $this->makeUser('pegawai');
        $finance = $this->makeUser('finance');
        $trip = $this->makeTrip($owner);

        Sanctum::actingAs($owner);
        $paymentId = $this->postJson("/api/trips/{$trip->id}/payments")->json('data.id');

        Sanctum::actingAs($finance);
        $this->postJson("/api/payments/{$paymentId}/reject", [
            'catatan' => 'Bukti belum lengkap.',
        ])->assertOk()->assertJsonPath('data.status', 'ditolak');

        $this->postJson("/api/payments/{$paymentId}/pay", ['metode' => 'tunai'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
