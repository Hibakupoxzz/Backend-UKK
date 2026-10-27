<?php

namespace App\Notifications;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TripDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Trip $trip,
        public TripStatus $status,
        public ?string $catatan = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
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
}
