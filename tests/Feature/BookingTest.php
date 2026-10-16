<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $first;

    private User $second;

    private Vehicle $vehicle;

    private Vehicle $otherVehicle;

    private Driver $driver;

    private Driver $otherDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret', 'role' => 'admin']);
        $this->first = User::create(['name' => 'First', 'email' => 'first@example.test', 'password' => 'secret', 'role' => 'approver']);
        $this->second = User::create(['name' => 'Second', 'email' => 'second@example.test', 'password' => 'secret', 'role' => 'approver']);
        $location = Location::create(['name' => 'Site', 'region' => 'Sulawesi', 'type' => 'mine']);
        $this->vehicle = Vehicle::create(['plate_number' => 'B 1001 AA', 'type' => 'passenger', 'ownership' => 'company', 'location_id' => $location->id]);
        $this->otherVehicle = Vehicle::create(['plate_number' => 'B 1002 AA', 'type' => 'goods', 'ownership' => 'rental', 'location_id' => $location->id]);
        $this->driver = Driver::create(['name' => 'Budi']);
        $this->otherDriver = Driver::create(['name' => 'Andi']);
    }

    private function data(): array
    {
        return [
            'requester_name' => 'Satriya', 'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id, 'destination' => 'Mine 2', 'purpose' => 'Inspection',
            'start_at' => '2026-10-20T08:00', 'end_at' => '2026-10-20T17:00',
            'approver_1_id' => $this->first->id, 'approver_2_id' => $this->second->id,
        ];
    }

    public function test_admin_creates_two_pending_approvals(): void
    {
        $this->actingAs($this->admin)->postJson('/api/bookings', $this->data())
            ->assertCreated()->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.start_at', '2026-10-20T08:00:00+07:00');

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_approvals', 2);
        $this->assertDatabaseHas('booking_logs', ['action' => 'BOOKING_CREATED', 'actor_id' => $this->admin->id]);
    }

    public function test_vehicle_and_driver_overlap(): void
    {
        $this->actingAs($this->admin)->postJson('/api/bookings', $this->data())->assertCreated();
        $vehicleConflict = array_merge($this->data(), ['driver_id' => $this->otherDriver->id, 'start_at' => '2026-10-20T10:00']);
        $this->postJson('/api/bookings', $vehicleConflict)->assertStatus(409);
        $driverConflict = array_merge($this->data(), ['vehicle_id' => $this->otherVehicle->id, 'start_at' => '2026-10-20T10:00']);
        $this->postJson('/api/bookings', $driverConflict)->assertStatus(409);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_logs', 1);
    }

    public function test_rejected_and_adjacent_slots(): void
    {
        $firstId = $this->actingAs($this->admin)->postJson('/api/bookings', $this->data())->assertCreated()->json('data.id');
        $adjacent = array_merge($this->data(), ['start_at' => '2026-10-20T17:00', 'end_at' => '2026-10-20T19:00']);
        $this->postJson('/api/bookings', $adjacent)->assertCreated();
        DB::table('bookings')->where('id', $firstId)->update(['status' => 'rejected']);
        $thirdId = $this->postJson('/api/bookings', $this->data())->assertCreated()->json('data.id');
        DB::table('bookings')->where('id', $thirdId)->update(['status' => 'completed', 'completed_at' => '2026-10-20 16:00:00+07']);
        $this->postJson('/api/bookings', array_merge($this->data(), ['start_at' => '2026-10-20T16:30']))->assertStatus(409);
    }

    public function test_datetime_input_is_wib(): void
    {
        $this->actingAs($this->admin)->postJson('/api/bookings', array_merge($this->data(), ['start_at' => '2026-10-20T08:00:00Z']))->assertUnprocessable();
        $this->postJson('/api/bookings', array_merge($this->data(), ['end_at' => '2026-10-20T07:00']))->assertUnprocessable();
        $this->postJson('/api/bookings', array_merge($this->data(), ['approver_2_id' => $this->first->id]))->assertUnprocessable();
    }

    public function test_approver_sees_only_assigned_bookings(): void
    {
        $bookingId = $this->actingAs($this->admin)->postJson('/api/bookings', $this->data())->assertCreated()->json('data.id');
        $outsider = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret', 'role' => 'approver']);
        $this->actingAs($outsider)->getJson('/api/bookings')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/bookings/'.$bookingId)->assertForbidden();
        $this->actingAs($this->second)->getJson('/api/bookings/'.$bookingId)->assertOk();
    }

    public function test_edit_before_first_decision(): void
    {
        $bookingId = $this->actingAs($this->admin)->postJson('/api/bookings', $this->data())->assertCreated()->json('data.id');
        $updated = array_merge($this->data(), [
            'requester_name' => 'Updated Employee', 'start_at' => '2026-10-21T08:00', 'end_at' => '2026-10-21T17:00',
        ]);

        $this->patchJson('/api/bookings/'.$bookingId, $updated)
            ->assertOk()->assertJsonPath('data.requester_name', 'Updated Employee')
            ->assertJsonPath('data.start_at', '2026-10-21T08:00:00+07:00');
        $this->assertDatabaseHas('booking_logs', ['booking_id' => $bookingId, 'action' => 'BOOKING_UPDATED']);

        DB::table('booking_approvals')->where('booking_id', $bookingId)->where('level', 1)
            ->update(['status' => 'approved', 'decided_at' => now()]);
        $this->patchJson('/api/bookings/'.$bookingId, $updated)->assertStatus(409);
        $this->assertDatabaseCount('booking_logs', 2);
    }

    public function test_edit_revalidates_without_self_conflict(): void
    {
        $bookingId = $this->actingAs($this->admin)->postJson('/api/bookings', $this->data())->assertCreated()->json('data.id');
        $this->patchJson('/api/bookings/'.$bookingId, $this->data())->assertOk();

        $other = array_merge($this->data(), [
            'vehicle_id' => $this->otherVehicle->id,
            'driver_id' => $this->otherDriver->id,
        ]);
        $otherId = $this->postJson('/api/bookings', $other)->assertCreated()->json('data.id');
        $conflictingEdit = array_merge($this->data(), ['vehicle_id' => $this->otherVehicle->id, 'driver_id' => $this->otherDriver->id]);

        $this->patchJson('/api/bookings/'.$bookingId, $conflictingEdit)->assertStatus(409);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'vehicle_id' => $this->vehicle->id]);
        $this->assertDatabaseCount('booking_logs', 3);

        $this->patchJson('/api/bookings/'.$otherId, array_merge($other, ['approver_2_id' => $this->first->id]))->assertUnprocessable();
    }
}
