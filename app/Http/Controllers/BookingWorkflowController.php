<?php

namespace App\Http\Controllers;

use App\Actions\BookingWorkflow;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingWorkflowController extends Controller
{
    public function decision(Request $request, Booking $booking, BookingWorkflow $workflow)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);
        $booking = $workflow->decide($request->user(), $booking, $data['decision']);

        return ['message' => 'Decision recorded.', 'data' => new BookingResource($booking->load(['vehicle.location', 'driver', 'approvals.approver', 'logs.actor']))];
    }

    public function complete(Request $request, Booking $booking, BookingWorkflow $workflow)
    {
        $booking = $workflow->complete($request->user(), $booking);

        return ['message' => 'Booking completed.', 'data' => new BookingResource($booking->load(['vehicle.location', 'driver', 'approvals.approver', 'logs.actor']))];
    }
}
