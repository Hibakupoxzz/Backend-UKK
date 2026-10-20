<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripNeed extends Model
{
    protected $fillable = ['trip_id', 'jenis', 'keterangan'];

    public function trip() { return $this->belongsTo(Trip::class); }
}
