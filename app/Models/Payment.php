<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'trip_id', 'requested_by', 'jumlah_diajukan', 'jumlah_disetujui',
        'status', 'metode', 'no_referensi', 'catatan', 'bukti_path',
        'verified_by', 'verified_at', 'paid_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status'           => PaymentStatus::class,
            'jumlah_diajukan'  => 'decimal:2',
            'jumlah_disetujui' => 'decimal:2',
            'verified_at'      => 'datetime',
            'paid_at'          => 'datetime',
        ];
    }

    public function trip()      { return $this->belongsTo(Trip::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function verifier()  { return $this->belongsTo(User::class, 'verified_by'); }
    public function payer()     { return $this->belongsTo(User::class, 'paid_by'); }

    // Admin/Finance melihat semua. Pengguna lain hanya pembayaran pada trip yang boleh dilihatnya.
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'finance'])) {
            return $query;
        }

        return $query->whereHas('trip', fn (Builder $q) => $q->visibleTo($user));
    }
}
