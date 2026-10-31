<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'department' => $this->department,
            'no_hp'      => $this->no_hp,
            'jabatan'    => $this->jabatan,
            'foto_url'   => $this->foto_path
                ? Storage::disk('public')->url($this->foto_path)
                : null,
            'is_active'  => (bool) $this->is_active,
            'role'       => $this->roles->pluck('name')->first(),
            'roles'      => $this->roles->pluck('name'),
            'atasan'     => $this->whenLoaded('atasan', fn () => $this->atasan
                ? ['id' => $this->atasan->id, 'name' => $this->atasan->name]
                : null),
            'created_at' => $this->created_at,
        ];
    }
}
