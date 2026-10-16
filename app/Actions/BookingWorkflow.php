<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingWorkflow
{
    public function __construct(private BookingAvailability $availability) {}

    public function create(User $actor, array $data): Booking
    {
        return DB::transaction(function () use ($actor, $data) {
            $this->ensureAvailable(null, $data);

            $booking = Booking::create(Arr::except($data, ['approver_1_id', 'approver_2_id']) + [
                'code' => 'TMP-'.Str::random(20), 'created_by' => $actor->id,
                'status' => 'pending', 'completed_at' => null,
            ]);
            $booking->code = 'BK-'.$booking->created_at->timezone('Asia/Jakarta')->format('Ymd').'-'.str_pad($booking->id, 3, '0', STR_PAD_LEFT);
            $booking->save();

            foreach ([1 => $data['approver_1_id'], 2 => $data['approver_2_id']] as $level => $approverId) {
                $booking->approvals()->create(['level' => $level, 'approver_id' => $approverId, 'status' => 'pending']);
            }
            $booking->logs()->create(['actor_id' => $actor->id, 'action' => 'BOOKING_CREATED']);

            return $booking;
        });
    }

    public function update(User $actor, Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($actor, $booking, $data) {
            abort_unless($booking->approvals()->where('level', 1)->where('status', 'pending')->exists(), 409);
            $this->ensureAvailable($booking->id, $data);

            $booking->update(Arr::except($data, ['approver_1_id', 'approver_2_id']));
            foreach ([1 => $data['approver_1_id'], 2 => $data['approver_2_id']] as $level => $approverId) {
                $booking->approvals()->where('level', $level)->update(['approver_id' => $approverId]);
            }
            $booking->logs()->create(['actor_id' => $actor->id, 'action' => 'BOOKING_UPDATED']);

            return $booking->refresh();
        });
    }

    public function decide(User $actor, Booking $booking, string $decision): Booking
    {
        return DB::transaction(function () use ($actor, $booking, $decision) {
            abort_unless($actor->role === 'approver', 403);
            abort_unless($booking->status === 'pending', 409);

            $approval = $booking->approvals()->where('approver_id', $actor->id)->first();
            abort_unless($approval !== null, 403);
            abort_unless($approval->status === 'pending', 409);
            if ($approval->level === 2) {
                abort_unless($booking->approvals()->where('level', 1)->where('status', 'approved')->exists(), 409);
            }

            if ($decision === 'approve' && $approval->level === 2) {
                $this->ensureAvailable($booking->id, [
                    'vehicle_id' => $booking->vehicle_id,
                    'driver_id' => $booking->driver_id,
                    'start_at' => $booking->start_at,
                    'end_at' => $booking->end_at,
                ]);
            }

            $approval->update(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'decided_at' => now()]);
            if ($decision === 'reject') {
                $booking->update(['status' => 'rejected']);
                $booking->logs()->create(['actor_id' => $actor->id, 'action' => 'BOOKING_REJECTED']);
            } else {
                $action = 'APPROVAL_LEVEL_'.$approval->level.'_APPROVED';
                if ($approval->level === 2) {
                    $booking->update(['status' => 'approved']);
                }
                $booking->logs()->create(['actor_id' => $actor->id, 'action' => $action]);
            }

            return $booking->refresh();
        });
    }

    public function complete(User $actor, Booking $booking): Booking
    {
        return DB::transaction(function () use ($actor, $booking) {
            abort_unless($actor->role === 'admin', 403);
            abort_unless($booking->status === 'approved' && ! $booking->start_at->isFuture(), 409);

            $booking->update(['status' => 'completed', 'completed_at' => now()]);
            $booking->logs()->create(['actor_id' => $actor->id, 'action' => 'BOOKING_COMPLETED']);

            return $booking->refresh();
        });
    }

    public function ensureAvailable(?int $exceptId, array $data): void
    {
        $resource = $this->availability->conflict(
            $exceptId, $data['vehicle_id'], $data['driver_id'], $data['start_at'], $data['end_at']
        );
        if ($resource !== null) {
            throw new ConflictHttpException(ucfirst($resource).' is already reserved for the selected schedule.');
        }
    }
}
