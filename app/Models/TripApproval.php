<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripApproval extends Model
{
    protected $fillable = ['trip_id', 'approver_id', 'level', 'status', 'catatan'];

    public function trip()     { return $this->belongsTo(Trip::class); }
    public function approver() { return $this->belongsTo(User::class, 'approver_id'); }
}
