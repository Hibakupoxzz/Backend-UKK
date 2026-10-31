<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $realisasi = (float) ($this->expenses_sum_jumlah ?? $this->expenses()->sum('jumlah'));
        $estimasi = $this->estimasi_biaya === null ? null : (float) $this->estimasi_biaya;

        return [
            'id'                => $this->id,
            'tujuan'            => $this->tujuan,
            'tanggal_berangkat' => $this->tanggal_berangkat->toDateString(),
            'tanggal_pulang'    => $this->tanggal_pulang->toDateString(),
            'keperluan'         => $this->keperluan,
            'status'            => $this->status->value,
            'status_label'      => $this->status->label(),
            'estimasi_biaya'    => $estimasi,
            'total_biaya'       => $realisasi,
            'selisih_biaya'     => $estimasi === null ? null : $realisasi - $estimasi,
            'created_at'        => $this->created_at,

            'user'      => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id, 'name' => $this->user->name, 'department' => $this->user->department,
            ]),
            'needs'     => $this->whenLoaded('needs', fn () => $this->needs->map(fn ($n) => [
                'id' => $n->id, 'jenis' => $n->jenis, 'keterangan' => $n->keterangan,
            ])),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($d) => [
                'id' => $d->id, 'jenis' => $d->jenis, 'url' => Storage::disk('public')->url($d->file_path),
            ])),
            'approvals' => $this->whenLoaded('approvals', fn () => $this->approvals->map(fn ($a) => [
                'id' => $a->id, 'status' => $a->status, 'catatan' => $a->catatan,
                'approver' => $a->approver?->name, 'at' => $a->created_at,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id'               => $p->id,
                'status'           => $p->status->value,
                'status_label'     => $p->status->label(),
                'jumlah_diajukan'  => (float) $p->jumlah_diajukan,
                'jumlah_disetujui' => $p->jumlah_disetujui === null ? null : (float) $p->jumlah_disetujui,
                'created_at'       => $p->created_at,
            ])),
        ];
    }
}
