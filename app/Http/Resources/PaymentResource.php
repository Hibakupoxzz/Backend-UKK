<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'trip_id'          => $this->trip_id,
            'status'           => $this->status->value,
            'status_label'     => $this->status->label(),
            'jumlah_diajukan'  => (float) $this->jumlah_diajukan,
            'jumlah_disetujui' => $this->jumlah_disetujui === null ? null : (float) $this->jumlah_disetujui,
            'metode'           => $this->metode,
            'no_referensi'     => $this->no_referensi,
            'catatan'          => $this->catatan,
            'bukti_url'        => $this->bukti_path
                ? Storage::disk('public')->url($this->bukti_path)
                : null,
            'verified_at'      => $this->verified_at?->toIso8601String(),
            'paid_at'          => $this->paid_at?->toIso8601String(),
            'created_at'       => $this->created_at,

            'trip' => $this->whenLoaded('trip', fn () => [
                'id'                => $this->trip->id,
                'tujuan'            => $this->trip->tujuan,
                'tanggal_berangkat' => $this->trip->tanggal_berangkat->toDateString(),
                'tanggal_pulang'    => $this->trip->tanggal_pulang->toDateString(),
                'status'            => $this->trip->status->value,
                'status_label'      => $this->trip->status->label(),
                'user'              => $this->trip->relationLoaded('user') && $this->trip->user
                    ? [
                        'id'         => $this->trip->user->id,
                        'name'       => $this->trip->user->name,
                        'department' => $this->trip->user->department,
                    ]
                    : null,
            ]),

            'requested_by' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id, 'name' => $this->requester->name,
            ]),
            'verified_by' => $this->whenLoaded('verifier', fn () => $this->verifier
                ? ['id' => $this->verifier->id, 'name' => $this->verifier->name]
                : null),
            'paid_by' => $this->whenLoaded('payer', fn () => $this->payer
                ? ['id' => $this->payer->id, 'name' => $this->payer->name]
                : null),
        ];
    }
}
