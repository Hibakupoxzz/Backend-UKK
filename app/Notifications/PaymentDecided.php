<?php

namespace App\Notifications;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentDecided extends Notification
{
    public function __construct(
        public Payment $payment,
        public PaymentStatus $status,
        public ?string $catatan = null,
    ) {}

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
        $jumlah = (float) ($p->jumlah_disetujui ?? $p->jumlah_diajukan);

        $title = match ($this->status) {
            PaymentStatus::Disetujui => 'Pembayaran disetujui',
            PaymentStatus::Dibayar   => 'Pembayaran telah ditransfer',
            default                  => 'Pengajuan pembayaran ditolak',
        };

        $message = match ($this->status) {
            PaymentStatus::Disetujui => "Pembayaran perjalanan ke {$t->tujuan} disetujui sebesar Rp " . number_format($jumlah, 0, ',', '.') . '.',
            PaymentStatus::Dibayar   => "Pembayaran perjalanan ke {$t->tujuan} sebesar Rp " . number_format($jumlah, 0, ',', '.') . ' telah diproses.',
            default                  => "Pengajuan pembayaran perjalanan ke {$t->tujuan} ditolak.",
        };

        return [
            'type'    => 'payment_decided',
            'title'   => $title,
            'message' => $message,
            'url'     => $this->urlFor($notifiable),
            'trip_id' => $t->id,
            'payment_id' => $p->id,
            'status'  => $this->status->value,
            'catatan' => $this->catatan,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment;
        $t = $p->trip;
        $jumlah = (float) ($p->jumlah_disetujui ?? $p->jumlah_diajukan);

        $subject = match ($this->status) {
            PaymentStatus::Disetujui => "Pembayaran perjalanan ke {$t->tujuan} disetujui",
            PaymentStatus::Dibayar   => "Pembayaran perjalanan ke {$t->tujuan} telah ditransfer",
            default                  => "Pengajuan pembayaran perjalanan ke {$t->tujuan} ditolak",
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Halo {$notifiable->name},")
            ->line(match ($this->status) {
                PaymentStatus::Disetujui => "Pengajuan pembayaran Anda untuk perjalanan ke {$t->tujuan} telah diverifikasi.",
                PaymentStatus::Dibayar   => "Pembayaran untuk perjalanan ke {$t->tujuan} telah diproses.",
                default                  => "Pengajuan pembayaran Anda untuk perjalanan ke {$t->tujuan} ditolak.",
            })
            ->line('Jumlah: Rp ' . number_format($jumlah, 0, ',', '.'));

        if ($this->status === PaymentStatus::Dibayar && $p->no_referensi) {
            $mail->line("Nomor referensi: {$p->no_referensi}");
        }

        if ($this->catatan) {
            $mail->line("Catatan finance: {$this->catatan}");
        }

        return $mail;
    }

    private function urlFor(object $notifiable): string
    {
        return match (true) {
            $notifiable->hasRole('pegawai') => '/pegawai/laporan',
            $notifiable->hasRole('atasan')  => '/atasan/laporan',
            $notifiable->hasRole('hrd')     => '/hrd/laporan',
            $notifiable->hasRole('finance') => '/finance/pembayaran',
            default                         => '/admin/reimbursement',
        };
    }
}
