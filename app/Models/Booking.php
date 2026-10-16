<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'code', 'created_by', 'vehicle_id', 'driver_id', 'requester_name',
        'destination', 'purpose', 'start_at', 'end_at', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'end_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function approvals()
    {
        return $this->hasMany(BookingApproval::class)->orderBy('level');
    }

    public function logs()
    {
        return $this->hasMany(BookingLog::class)->orderBy('created_at');
    }

    public function scopeFiltered(Builder $query, ?string $from, ?string $to, ?string $status): Builder
    {
        if ($from !== null) {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'Asia/Jakarta')->utc();
            $query->where('created_at', '>=', $start);
        }
        if ($to !== null) {
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'Asia/Jakarta')->addDay()->utc();
            $query->where('created_at', '<', $end);
        }

        return $status === null ? $query : $query->where('status', $status);
    }
}
