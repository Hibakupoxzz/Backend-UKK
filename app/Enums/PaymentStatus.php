<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Diajukan  = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak   = 'ditolak';
    case Dibayar   = 'dibayar';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan  => 'Menunggu Verifikasi',
            self::Disetujui => 'Siap Dibayar',
            self::Ditolak   => 'Ditolak',
            self::Dibayar   => 'Dibayar',
        };
    }

    /** Status berikutnya yang boleh dituju dari status ini. */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Diajukan  => [self::Disetujui, self::Ditolak],
            self::Disetujui => [self::Dibayar, self::Ditolak],
            self::Ditolak   => [],
            self::Dibayar   => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
