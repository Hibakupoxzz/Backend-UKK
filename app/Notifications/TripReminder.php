<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TripReminder extends Notification
{
    public function __construct(public Trip $trip) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notificationPreference('travel_reminder')
            ? $notifiable->notificationChannels()
            : [];
    }

    public function toDatabase(object $notifiable): array
    {
        $trip = $this->trip;

        return [
            'type'    => 'trip_reminder',
            'title'   => 'Pengingat perjalanan',
            'message' => "Perjalanan dinas ke {$trip->tujuan} dijadwalkan pada {$trip->tanggal_berangkat->format('d-m-Y')}. Siapkan dokumen dan tiket Anda.",
            'url'     => $this->urlFor($notifiable, $trip),
            'trip_id' => $trip->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $trip = $this->trip;

        return (new MailMessage)
            ->subject("Pengingat perjalanan dinas: {$trip->tujuan}")
            ->greeting("Halo {$notifiable->name},")
            ->line("Perjalanan dinas Anda ke {$trip->tujuan} akan berangkat pada {$trip->tanggal_berangkat->format('d-m-Y')}.")
            ->line("Tanggal kembali: {$trip->tanggal_pulang->format('d-m-Y')}")
            ->line("Keperluan: {$trip->keperluan}")
            ->line('Pastikan dokumen, tiket, dan kebutuhan akomodasi sudah siap.');
    }

    private function urlFor(object $notifiable, Trip $trip): string
    {
        return match (true) {
            $notifiable->hasRole('atasan')  => "/atasan/perjalanan-tim/{$trip->id}",
            $notifiable->hasRole('hrd')     => "/hrd/detail-perjalanan/{$trip->id}",
            $notifiable->hasRole('finance') => '/finance/laporan',
            $notifiable->hasRole('admin')   => "/admin/DetailPerjalanan?id={$trip->id}",
            default                         => '/pegawai/laporan',
        };
    }
}
