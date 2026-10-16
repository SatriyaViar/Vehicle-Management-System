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

    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        if (isset($filters['from'])) {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], 'Asia/Jakarta')->utc();
            $query->where('created_at', '>=', $start);
        }
        if (isset($filters['to'])) {
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], 'Asia/Jakarta')->addDay()->utc();
            $query->where('created_at', '<', $end);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        foreach (['vehicle_id' => 'vehicle_id', 'driver_id' => 'driver_id'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (isset($filters['approver_id'])) {
            $query->whereHas('approvals', fn (Builder $approvals) => $approvals->where('approver_id', $filters['approver_id']));
        }

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn (Builder $matches) => $matches
                ->whereRaw("code ILIKE ? ESCAPE E'\\\\'", [$pattern])
                ->orWhereRaw("requester_name ILIKE ? ESCAPE E'\\\\'", [$pattern])
                ->orWhereRaw("destination ILIKE ? ESCAPE E'\\\\'", [$pattern]));
        }

        return $query;
    }
}
