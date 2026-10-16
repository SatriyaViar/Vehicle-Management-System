<?php

namespace App\Http\Controllers;

use App\Actions\BookingWorkflow;
use App\Http\Requests\BookingFilterRequest;
use App\Http\Requests\SaveBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(BookingFilterRequest $request)
    {
        $filters = $request->validated();
        $bookings = Booking::with(['vehicle.location', 'driver', 'approvals.approver', 'logs.actor'])
            ->when($request->user()->role === 'approver', fn ($query) => $query->whereHas('approvals',
                fn ($approval) => $approval->where('approver_id', $request->user()->id)))
            ->filtered($filters)
            ->latest()->get();

        return ['data' => BookingResource::collection($bookings)];
    }

    public function filterOptions(): array
    {
        return [
            'vehicles' => Vehicle::query()->orderBy('plate_number')->get(['id', 'plate_number']),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name']),
            'approvers' => User::query()->where('role', 'approver')->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless($request->user()->role === 'admin' || $booking->approvals()->where('approver_id', $request->user()->id)->exists(), 403);

        return ['data' => new BookingResource($booking->load(['vehicle.location', 'driver', 'approvals.approver', 'logs.actor']))];
    }

    public function store(SaveBookingRequest $request, BookingWorkflow $workflow)
    {
        $booking = $workflow->create($request->user(), $request->bookingData());

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => new BookingResource($booking->load(['vehicle.location', 'driver', 'approvals.approver', 'logs.actor'])),
        ], 201);
    }

    public function update(SaveBookingRequest $request, Booking $booking, BookingWorkflow $workflow)
    {
        $booking = $workflow->update($request->user(), $booking, $request->bookingData());

        return ['message' => 'Booking updated successfully.', 'data' => new BookingResource($booking->load(['vehicle.location', 'driver', 'approvals.approver', 'logs.actor']))];
    }
}
