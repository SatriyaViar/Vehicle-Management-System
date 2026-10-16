<?php

namespace App\Actions;

use App\Models\Booking;
use Carbon\CarbonInterface;

class BookingAvailability
{
    public function conflict(?int $exceptId, int $vehicleId, int $driverId, CarbonInterface $start, CarbonInterface $end): ?string
    {
        // ponytail: app-level overlap checks can race under simultaneous writes; add PostgreSQL exclusion constraints if concurrent booking writers become necessary.
        foreach (['vehicle_id' => $vehicleId, 'driver_id' => $driverId] as $column => $id) {
            $query = Booking::where($column, $id)
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start);
            if ($exceptId !== null) {
                $query->whereKeyNot($exceptId);
            }
            if ($query->exists()) {
                return $column === 'vehicle_id' ? 'vehicle' : 'driver';
            }
        }

        return null;
    }
}
