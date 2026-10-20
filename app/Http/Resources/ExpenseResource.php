<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'trip_id'    => $this->trip_id,
            'jenis'      => $this->jenis,
            'jumlah'     => (float) $this->jumlah,
            'tanggal'    => $this->tanggal->toDateString(),
            'keterangan' => $this->keterangan,
            'receipts'   => $this->whenLoaded('receipts', fn () => $this->receipts->map(fn ($r) => [
                'id'  => $r->id,
                'url' => Storage::disk('public')->url($r->file_path),
            ])),
        ];
    }
}
