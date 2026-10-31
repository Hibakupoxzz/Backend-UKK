<?php

namespace App\Policies;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    private function isOwner(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id;
    }

    private function isAtasanOf(User $user, Trip $trip): bool
    {
        return $trip->user->atasan_id === $user->id;
    }

    // Lihat: pemilik, atasan langsung, admin, atau peran pembaca org-wide (HRD/Finance)
    public function view(User $user, Trip $trip): bool
    {
        return $this->isOwner($user, $trip)
            || $user->hasAnyRole(['admin', 'hrd', 'finance'])
            || $this->isAtasanOf($user, $trip);
    }

    // Ubah: hanya pemilik, dan hanya saat draft atau ditolak
    public function update(User $user, Trip $trip): bool
    {
        return $this->isOwner($user, $trip)
            && in_array($trip->status, [TripStatus::Draft, TripStatus::Ditolak], true);
    }

    // Hapus: hanya pemilik, hanya saat draft
    public function delete(User $user, Trip $trip): bool
    {
        return $this->isOwner($user, $trip) && $trip->status === TripStatus::Draft;
    }

    // Ajukan, mulai, selesai, unggah dokumen: pemilik
    public function submit(User $user, Trip $trip): bool { return $this->isOwner($user, $trip); }
    public function start(User $user, Trip $trip): bool  { return $this->isOwner($user, $trip); }
    public function finish(User $user, Trip $trip): bool { return $this->isOwner($user, $trip); }
    public function manageDocuments(User $user, Trip $trip): bool
    {
        return $this->isOwner($user, $trip);
    }

    // Setujui / tolak / nyatakan lengkap: atasan langsung atau admin, tidak boleh untuk trip sendiri
    public function approve(User $user, Trip $trip): bool
    {
        return ! $this->isOwner($user, $trip)
            && ($user->hasRole('admin') || $this->isAtasanOf($user, $trip));
    }
    // Catat/ubah/hapus pengeluaran: pemilik, saat perjalanan berjalan atau selesai
    public function manageExpenses(User $user, Trip $trip): bool
    {
        return $this->isOwner($user, $trip)
            && in_array($trip->status, [TripStatus::Berjalan, TripStatus::Selesai], true);
    }
}
