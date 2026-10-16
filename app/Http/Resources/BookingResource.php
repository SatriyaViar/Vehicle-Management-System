<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'requester_name' => $this->requester_name,
            'vehicle_id' => $this->vehicle_id,
            'vehicle' => $this->vehicle,
            'driver_id' => $this->driver_id,
            'driver' => $this->driver,
            'destination' => $this->destination,
            'purpose' => $this->purpose,
            'start_at' => $this->wib($this->start_at),
            'end_at' => $this->wib($this->end_at),
            'created_at' => $this->wib($this->created_at),
            'completed_at' => $this->wib($this->completed_at),
            'status' => $this->status,
            'approvals' => $this->approvals->map(fn ($approval) => [
                'level' => $approval->level,
                'approver_id' => $approval->approver_id,
                'approver' => $approval->approver?->name,
                'status' => $approval->status,
                'decided_at' => $this->wib($approval->decided_at),
            ]),
            'logs' => $this->logs->map(fn ($log) => [
                'action' => $log->action,
                'actor' => $log->actor?->name,
                'created_at' => $this->wib($log->created_at),
            ]),
        ];
    }

    private function wib($date): ?string
    {
        return $date ? Carbon::parse($date)->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP') : null;
    }
}
