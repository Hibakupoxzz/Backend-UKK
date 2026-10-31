<?php

namespace App\Models;

use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $fillable = [
        'user_id', 'tujuan', 'tanggal_berangkat',
        'tanggal_pulang', 'keperluan', 'estimasi_biaya', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status'            => TripStatus::class,
            'tanggal_berangkat' => 'date',
            'tanggal_pulang'    => 'date',
            'estimasi_biaya'    => 'decimal:2',
        ];
    }

    public function user()      { return $this->belongsTo(User::class); }
    public function needs()     { return $this->hasMany(TripNeed::class); }
    public function documents() { return $this->hasMany(TripDocument::class); }
    public function approvals() { return $this->hasMany(TripApproval::class); }
    public function expenses()  { return $this->hasMany(Expense::class); }
    public function payments()  { return $this->hasMany(Payment::class); }

    // Total biaya dihitung dari data pengeluaran, tidak disimpan manual
    public function getTotalBiayaAttribute()
    {
        return $this->expenses()->sum('jumlah');
    }

    // Pencarian: pegawai, tujuan, tanggal, status
    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['tujuan'] ?? null, fn ($q, $v) => $q->where('tujuan', 'like', "%{$v}%"))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_berangkat', '>=', $v))
            ->when($f['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_berangkat', '<=', $v));
    }

    // Admin/HRD/Finance: semua (baca org-wide). Atasan: bawahan + miliknya. Pegawai: miliknya.
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'hrd', 'finance'])) {
            return $query;
        }

        if ($user->hasRole('atasan')) {
            return $query->whereIn('user_id', $user->bawahan()->pluck('id')->push($user->id));
        }

        return $query->where('user_id', $user->id);
    }
}
