<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingApproval extends Model
{
    protected $fillable = ['booking_id', 'approver_id', 'level', 'status', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
