<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // hak akses diatur di TripPolicy
    }

    public function rules(): array
    {
        return [
            'tujuan'            => ['required', 'string', 'max:255'],
            'tanggal_berangkat' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_pulang'    => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'keperluan'         => ['required', 'string'],
            'estimasi_biaya'    => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'needs'             => ['sometimes', 'array'],
            'needs.*.jenis'     => ['required', 'string', 'in:transportasi,penginapan'],
            'needs.*.keterangan' => ['nullable', 'string'],
        ];
    }
}
