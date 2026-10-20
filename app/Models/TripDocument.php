<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDocument extends Model
{
    protected $fillable = ['trip_id', 'file_path', 'jenis'];

    public function trip() { return $this->belongsTo(Trip::class); }
}
