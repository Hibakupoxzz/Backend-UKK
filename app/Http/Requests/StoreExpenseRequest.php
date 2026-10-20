<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // hak akses diatur di TripPolicy
    }

    public function rules(): array
    {
        return [
            'jenis'      => ['required', 'in:transportasi,penginapan,konsumsi,lainnya'],
            'jumlah'     => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'tanggal'    => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
