<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRequested extends Notification
{
    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notificationPreference('payment_updates')
            ? $notifiable->notificationChannels()
            : [];
    }

    public function toDatabase(object $notifiable): array
    {
        $p = $this->payment;
        $t = $p->trip;

        return [
            'type'    => 'payment_requested',
            'title'   => 'Pengajuan pembayaran baru',
            'message' => "{$p->requester->name} mengajukan pembayaran perjalanan ke {$t->tujuan}.",
            'url'     => $notifiable->hasRole('finance') ? '/finance/pembayaran' : '/admin/reimbursement',
            'trip_id' => $t->id,
            'payment_id' => $p->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment;
        $t = $p->trip;

        return (new MailMessage)
            ->subject("Pengajuan pembayaran baru: {$t->tujuan}")
            ->greeting("Halo {$notifiable->name},")
            ->line("{$p->requester->name} mengajukan pembayaran perjalanan dinas ke {$t->tujuan}.")
            ->line('Jumlah diajukan: Rp ' . number_format((float) $p->jumlah_diajukan, 0, ',', '.'))
            ->line("Tanggal perjalanan: {$t->tanggal_berangkat->format('d-m-Y')} s/d {$t->tanggal_pulang->format('d-m-Y')}")
            ->line('Silakan buka aplikasi EduAbsen untuk memverifikasi pengajuan ini.');
    }
}
