<?php

namespace App\Notifications;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TripDecided extends Notification
{
    public function __construct(
        public Trip $trip,
        public TripStatus $status,
        public ?string $catatan = null,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notificationChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        $trip = $this->trip;
        $disetujui = $this->status === TripStatus::Disetujui;

        return [
            'type'    => 'trip_decided',
            'title'   => $disetujui ? 'Pengajuan perjalanan disetujui' : 'Pengajuan perjalanan ditolak',
            'message' => $disetujui
                ? "Perjalanan dinas Anda ke {$trip->tujuan} telah disetujui."
                : "Perjalanan dinas Anda ke {$trip->tujuan} ditolak.",
            'url'     => $this->urlFor($notifiable, $trip),
            'trip_id' => $trip->id,
            'status'  => $this->status->value,
            'catatan' => $this->catatan,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->trip;
        $disetujui = $this->status === TripStatus::Disetujui;

        $mail = (new MailMessage)
            ->subject($disetujui
                ? "Perjalanan dinas ke {$t->tujuan} disetujui"
                : "Perjalanan dinas ke {$t->tujuan} ditolak")
            ->greeting("Halo {$notifiable->name},")
            ->line($disetujui
                ? "Pengajuan perjalanan dinas Anda ke {$t->tujuan} telah disetujui."
                : "Pengajuan perjalanan dinas Anda ke {$t->tujuan} ditolak.")
            ->line("Tanggal: {$t->tanggal_berangkat->format('d-m-Y')} s/d {$t->tanggal_pulang->format('d-m-Y')}");

        if ($this->catatan) {
            $mail->line("Catatan atasan: {$this->catatan}");
        }

        return $mail->line($disetujui
            ? 'Anda dapat memulai perjalanan melalui aplikasi EduAbsen.'
            : 'Silakan perbaiki data perjalanan lalu ajukan kembali.');
    }

    private function urlFor(object $notifiable, Trip $trip): string
    {
        return match (true) {
            $notifiable->hasRole('pegawai') => '/pegawai/laporan',
            $notifiable->hasRole('atasan')  => "/atasan/perjalanan-tim/{$trip->id}",
            $notifiable->hasRole('hrd')     => "/hrd/detail-perjalanan/{$trip->id}",
            $notifiable->hasRole('finance') => '/finance/laporan',
            default                         => "/admin/DetailPerjalanan?id={$trip->id}",
        };
    }
}
