<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $year = CarbonImmutable::now('Asia/Jakarta')->year;
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta')->utc();
        $end = $start->addYear();
        $counts = Booking::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->selectRaw("EXTRACT(MONTH FROM completed_at AT TIME ZONE 'Asia/Jakarta')::int AS month, COUNT(*)::int AS trips")
            ->groupBy('month')
            ->pluck('trips', 'month');

        return [
            'year' => $year,
            'total_bookings' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'approved' => Booking::where('status', 'approved')->count(),
            'completed_trips' => Booking::where('status', 'completed')->count(),
            'monthly_usage' => collect(range(1, 12))->map(fn ($month) => [
                'month' => $month,
                'trips' => (int) ($counts[$month] ?? 0),
            ])->values(),
        ];
    }
}
