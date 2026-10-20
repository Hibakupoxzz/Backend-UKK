<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'tujuan'            => $this->tujuan,
            'tanggal_berangkat' => $this->tanggal_berangkat->toDateString(),
            'tanggal_pulang'    => $this->tanggal_pulang->toDateString(),
            'keperluan'         => $this->keperluan,
            'status'            => $this->status->value,
            'status_label'      => $this->status->label(),
            'total_biaya'       => (float) ($this->expenses_sum_jumlah ?? $this->expenses()->sum('jumlah')),
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
        ];
    }
}
