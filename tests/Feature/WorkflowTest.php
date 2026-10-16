<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $first;

    private User $second;

    private int $vehicleId;

    private int $driverId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret', 'role' => 'admin']);
        $this->first = User::create(['name' => 'First', 'email' => 'first@example.test', 'password' => 'secret', 'role' => 'approver']);
        $this->second = User::create(['name' => 'Second', 'email' => 'second@example.test', 'password' => 'secret', 'role' => 'approver']);
        $location = Location::create(['name' => 'Site', 'region' => 'Sulawesi', 'type' => 'mine']);
        $this->vehicleId = Vehicle::create(['plate_number' => 'B 1001 AA', 'type' => 'passenger', 'ownership' => 'company', 'location_id' => $location->id])->id;
        $this->driverId = Driver::create(['name' => 'Budi'])->id;
    }

    private function createBooking(): int
    {
        return $this->actingAs($this->admin)->postJson('/api/bookings', [
            'requester_name' => 'Satriya', 'vehicle_id' => $this->vehicleId, 'driver_id' => $this->driverId,
            'destination' => 'Mine 2', 'purpose' => 'Inspection',
            'start_at' => '2026-10-20T08:00', 'end_at' => '2026-10-20T17:00',
            'approver_1_id' => $this->first->id, 'approver_2_id' => $this->second->id,
        ])->assertCreated()->json('data.id');
    }

    public function test_approval_order_and_roles(): void
    {
        $bookingId = $this->createBooking();
        $this->actingAs($this->second)->postJson("/api/bookings/$bookingId/decision", ['decision' => 'approve', 'level' => 1])->assertStatus(409);
        $outsider = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret', 'role' => 'approver']);
        $this->actingAs($outsider)->postJson("/api/bookings/$bookingId/decision", ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($this->first)->postJson("/api/bookings/$bookingId/decision", [
            'decision' => 'approve', 'level' => 2, 'approver_id' => $this->second->id,
        ])->assertOk()->assertJsonPath('data.status', 'pending');
        $this->postJson("/api/bookings/$bookingId/decision", ['decision' => 'approve'])->assertStatus(409);
        $this->assertDatabaseCount('booking_logs', 2);

        $this->actingAs($this->second)->postJson("/api/bookings/$bookingId/decision", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseCount('booking_logs', 3);
    }

    public function test_reject_stops_flow(): void
    {
        $firstBooking = $this->createBooking();
        $this->actingAs($this->first)->postJson("/api/bookings/$firstBooking/decision", ['decision' => 'reject'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->actingAs($this->second)->postJson("/api/bookings/$firstBooking/decision", ['decision' => 'reject'])->assertStatus(409);

        $secondBooking = $this->createBooking();
        $this->actingAs($this->first)->postJson("/api/bookings/$secondBooking/decision", ['decision' => 'approve'])->assertOk();
        $this->actingAs($this->second)->postJson("/api/bookings/$secondBooking/decision", ['decision' => 'reject'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('booking_logs', ['booking_id' => $firstBooking, 'action' => 'BOOKING_REJECTED', 'actor_id' => $this->first->id]);
        $this->assertDatabaseHas('booking_logs', ['booking_id' => $secondBooking, 'action' => 'BOOKING_REJECTED', 'actor_id' => $this->second->id]);
    }

    public function test_decision_recheck_and_replay(): void
    {
        $bookingId = $this->createBooking();
        $this->actingAs($this->first)->postJson("/api/bookings/$bookingId/decision", ['decision' => 'approve'])->assertOk();
        $conflictId = DB::table('bookings')->insertGetId([
            'code' => 'BK-CONFLICT', 'created_by' => $this->admin->id, 'vehicle_id' => $this->vehicleId,
            'driver_id' => $this->driverId, 'requester_name' => 'Other', 'destination' => 'Mine 3',
            'purpose' => 'Inspection', 'start_at' => '2026-10-20 02:00:00+00', 'end_at' => '2026-10-20 04:00:00+00',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->second)->postJson("/api/bookings/$bookingId/decision", ['decision' => 'approve'])->assertStatus(409);
        $this->assertDatabaseHas('booking_approvals', ['booking_id' => $bookingId, 'level' => 2, 'status' => 'pending']);
        $this->assertDatabaseCount('booking_logs', 2);

        $this->postJson("/api/bookings/$bookingId/decision", ['decision' => 'reject'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('bookings', ['id' => $conflictId, 'status' => 'pending']);
    }

    public function test_completion_uses_server_time_and_keeps_slot(): void
    {
        $bookingId = $this->createBooking();
        $this->actingAs($this->first)->postJson("/api/bookings/$bookingId/decision", ['decision' => 'approve'])->assertOk();
        $this->actingAs($this->second)->postJson("/api/bookings/$bookingId/decision", ['decision' => 'approve'])->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-10-20T07:59:00+07:00'));
        $this->actingAs($this->admin)->postJson("/api/bookings/$bookingId/complete")->assertStatus(409);
        $this->travelTo(CarbonImmutable::parse('2026-10-20T08:00:00+07:00'));
        $this->postJson("/api/bookings/$bookingId/complete", ['completed_at' => '2026-10-19T00:00:00Z'])
            ->assertOk()->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completed_at', '2026-10-20T08:00:00+07:00');

        $overlap = $this->postJson('/api/bookings', [
            'requester_name' => 'Other', 'vehicle_id' => $this->vehicleId, 'driver_id' => $this->driverId,
            'destination' => 'Mine 3', 'purpose' => 'Return', 'start_at' => '2026-10-20T16:30', 'end_at' => '2026-10-20T18:00',
            'approver_1_id' => $this->first->id, 'approver_2_id' => $this->second->id,
        ]);
        $overlap->assertStatus(409);
        $this->postJson("/api/bookings/$bookingId/complete")->assertStatus(409);
        $this->assertDatabaseCount('booking_logs', 4);
    }
}
