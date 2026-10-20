<?php

namespace App\Enums;

enum TripStatus: string
{
    case Draft     = 'draft';
    case Diajukan  = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak   = 'ditolak';
    case Berjalan  = 'berjalan';
    case Selesai   = 'selesai';
    case Lengkap   = 'lengkap';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Status berikutnya yang boleh dituju dari status ini. */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft     => [self::Diajukan],
            self::Diajukan  => [self::Disetujui, self::Ditolak],
            self::Ditolak   => [self::Diajukan],   // diperbaiki lalu diajukan ulang
            self::Disetujui => [self::Berjalan],
            self::Berjalan  => [self::Selesai],
            self::Selesai   => [self::Lengkap],
            self::Lengkap   => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
