<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['booking_id', 'actor_id', 'action'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
