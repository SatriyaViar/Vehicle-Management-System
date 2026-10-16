<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['plate_number', 'type', 'ownership', 'location_id'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
