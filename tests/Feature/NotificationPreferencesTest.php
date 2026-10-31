<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\PaymentDecided;
use App\Notifications\TripReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $preferences = []): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(
            $preferences === [] ? [] : ['notification_preferences' => $preferences]
        );
        $user->assignRole($role);

        return $user;
    }

    private function makeTrip(User $owner, array $overrides = []): Trip
    {
        return Trip::create([
            'user_id' => $owner->id,
            'tujuan' => 'Semarang',
            'tanggal_berangkat' => now()->addDay()->toDateString(),
            'tanggal_pulang' => now()->addDays(3)->toDateString(),
            'keperluan' => 'Kunjungan kerja',
            'status' => 'draft',
            ...$overrides,
        ]);
    }

    public function test_preferences_default_to_enabled_and_can_be_updated(): void
    {
        $user = $this->makeUser('pegawai');

        Sanctum::actingAs($user);

        $this->getJson('/api/me/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.email', true)
            ->assertJsonPath('data.in_app', true)
            ->assertJsonPath('data.travel_reminder', true)
            ->assertJsonPath('data.payment_updates', true);

        $this->putJson('/api/me/notifications/preferences', [
            'in_app' => false,
            'payment_updates' => false,
        ])->assertOk()
            ->assertJsonPath('data.email', true)
            ->assertJsonPath('data.in_app', false)
            ->assertJsonPath('data.travel_reminder', true)
            ->assertJsonPath('data.payment_updates', false);

        $this->assertFalse($user->fresh()->notificationPreference('in_app'));
        $this->assertFalse($user->fresh()->notificationPreference('payment_updates'));
        $this->assertTrue($user->fresh()->notificationPreference('email'));
    }

    public function test_preferences_are_scoped_to_the_authenticated_user(): void
    {
        $user = $this->makeUser('pegawai');
        $other = $this->makeUser('pegawai');

        Sanctum::actingAs($user);
        $this->putJson('/api/me/notifications/preferences', ['email' => false])->assertOk();

        $this->assertFalse($user->fresh()->notificationPreference('email'));
        $this->assertTrue($other->fresh()->notificationPreference('email'));
    }

    public function test_disabling_channels_prevents_trip_notifications(): void
    {
        Mail::fake();

        $atasan = $this->makeUser('atasan', ['in_app' => false, 'email' => false]);
        $owner = $this->makeUser('pegawai');
        $owner->update(['atasan_id' => $atasan->id]);

        $trip = $this->makeTrip($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/trips/{$trip->id}/submit")->assertOk();

        $this->assertSame(0, $atasan->notifications()->count());
        Mail::assertNothingSent();
    }

    public function test_payment_preference_suppresses_payment_notifications(): void
    {
        Mail::fake();

        $owner = $this->makeUser('pegawai', ['payment_updates' => false]);
        $trip = $this->makeTrip($owner, ['status' => 'selesai']);

        $payment = Payment::create([
            'trip_id' => $trip->id,
            'requested_by' => $owner->id,
            'jumlah_diajukan' => 100000,
            'status' => 'diajukan',
        ]);

        $owner->notify(new PaymentDecided($payment->load('trip', 'requester'), PaymentStatus::Disetujui));

        $this->assertSame(0, $owner->notifications()->count());
        Mail::assertNothingSent();
    }

    public function test_reminder_command_sends_once_and_respects_preference(): void
    {
        Mail::fake();

        $default = $this->makeUser('pegawai');
        $disabled = $this->makeUser('pegawai', ['travel_reminder' => false]);

        $this->makeTrip($default, ['status' => 'disetujui']);
        $this->makeTrip($disabled, ['status' => 'disetujui']);

        $this->artisan('trips:send-reminders')->assertExitCode(0);

        $this->assertSame(1, $default->notifications()->where('type', TripReminder::class)->count());
        $this->assertSame(0, $disabled->notifications()->count());

        // Menjalankan ulang di hari yang sama tidak boleh menduplikasi pengingat.
        $this->artisan('trips:send-reminders')->assertExitCode(0);

        $this->assertSame(1, $default->notifications()->where('type', TripReminder::class)->count());
    }
}
