<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TripSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Trip $trip) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->trip;

        return (new MailMessage)
            ->subject("Pengajuan perjalanan dinas baru: {$t->tujuan}")
            ->greeting("Halo {$notifiable->name},")
            ->line("{$t->user->name} mengajukan perjalanan dinas dan menunggu persetujuan Anda.")
            ->line("Tujuan: {$t->tujuan}")
            ->line("Tanggal: {$t->tanggal_berangkat->format('d-m-Y')} s/d {$t->tanggal_pulang->format('d-m-Y')}")
            ->line("Keperluan: {$t->keperluan}")
            ->line('Silakan buka aplikasi EduAbsen untuk menyetujui atau menolak pengajuan ini.');
    }
}
