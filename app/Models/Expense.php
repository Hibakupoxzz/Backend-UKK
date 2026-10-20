<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['trip_id', 'jenis', 'jumlah', 'tanggal', 'keterangan'];

    protected function casts(): array
    {
        return ['jumlah' => 'decimal:2', 'tanggal' => 'date'];
    }

    public function trip()     { return $this->belongsTo(Trip::class); }
    public function receipts() { return $this->hasMany(ExpenseReceipt::class); }
}
