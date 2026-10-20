<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripWorkflowService
{
    public function submit(Trip $trip): Trip
    {
        return $this->move($trip, TripStatus::Diajukan);
    }

    public function start(Trip $trip): Trip
    {
        return $this->move($trip, TripStatus::Berjalan);
    }

    public function finish(Trip $trip): Trip
    {
        return $this->move($trip, TripStatus::Selesai);
    }

    public function approve(Trip $trip, User $approver, ?string $catatan = null): Trip
    {
        return $this->decide($trip, $approver, TripStatus::Disetujui, $catatan);
    }

    public function reject(Trip $trip, User $approver, string $catatan): Trip
    {
        return $this->decide($trip, $approver, TripStatus::Ditolak, $catatan);
    }

    public function complete(Trip $trip): Trip
    {
        if ($trip->status === TripStatus::Selesai) {
            $tanpaBukti = $trip->expenses()->doesntHave('receipts')->count();

            if ($tanpaBukti > 0) {
                throw ValidationException::withMessages([
                    'status' => ["Masih ada {$tanpaBukti} pengeluaran tanpa bukti transaksi."],
                ]);
            }
        }

        return $this->move($trip, TripStatus::Lengkap);
    }

    private function decide(Trip $trip, User $approver, TripStatus $status, ?string $catatan): Trip
    {
        return DB::transaction(function () use ($trip, $approver, $status, $catatan) {
            $this->move($trip, $status);

            $trip->approvals()->create([
                'approver_id' => $approver->id,
                'level'       => 1,
                'status'      => $status->value,
                'catatan'     => $catatan,
            ]);

            return $trip;
        });
    }

    private function move(Trip $trip, TripStatus $next): Trip
    {
        if (! $trip->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ["Perjalanan berstatus '{$trip->status->value}' tidak bisa diubah ke '{$next->value}'."],
            ]);
        }

        $trip->update(['status' => $next]);

        return $trip;
    }
}
