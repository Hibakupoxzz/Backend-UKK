<?php

namespace App\Console\Commands;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Notifications\TripReminder;
use Illuminate\Console\Command;

class SendTripReminders extends Command
{
    protected $signature = 'trips:send-reminders {--days=1 : Jarak hari sebelum keberangkatan}';

    protected $description = 'Kirim pengingat untuk perjalanan dinas yang akan segera berangkat.';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $date = now()->addDays($days)->toDateString();

        $trips = Trip::with('user')
            ->whereDate('tanggal_berangkat', $date)
            ->whereIn('status', [TripStatus::Disetujui, TripStatus::Berjalan])
            ->get();

        $sent = 0;

        foreach ($trips as $trip) {
            $user = $trip->user;

            if (! $user || ! $user->notificationPreference('travel_reminder')) {
                continue;
            }

            // Hindari pengingat ganda bila perintah dijalankan lebih dari sekali sehari.
            $alreadySent = $user->notifications()
                ->where('type', TripReminder::class)
                ->where('data->trip_id', $trip->id)
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $user->notify(new TripReminder($trip));
            $sent++;
        }

        $this->info("Pengingat perjalanan terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
