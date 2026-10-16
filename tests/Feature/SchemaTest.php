<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_check(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'role'));

        $this->expectException(QueryException::class);
        DB::table('users')->insert([
            'name' => 'Unknown', 'email' => 'unknown@example.test', 'password' => 'secret', 'role' => 'unknown',
        ]);
    }

    public function test_completed_requires_timestamp(): void
    {
        $bookingId = $this->booking();
        $this->expectException(QueryException::class);
        DB::table('bookings')->where('id', $bookingId)->update(['status' => 'completed']);
    }

    public function test_approval_level_unique(): void
    {
        $bookingId = $this->booking();
        $approver = DB::table('users')->insertGetId([
            'name' => 'Approver', 'email' => 'approver@example.test', 'password' => 'secret', 'role' => 'approver',
        ]);
        DB::table('booking_approvals')->insert([
            'booking_id' => $bookingId, 'approver_id' => $approver, 'level' => 1, 'status' => 'pending',
        ]);

        $this->expectException(QueryException::class);
        DB::table('booking_approvals')->insert([
            'booking_id' => $bookingId, 'approver_id' => $approver, 'level' => 1, 'status' => 'pending',
        ]);
    }

    private function booking(): int
    {
        $this->assertTrue(Schema::hasTable('bookings'));

        $user = DB::table('users')->insertGetId([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret', 'role' => 'admin',
        ]);
        $location = DB::table('locations')->insertGetId([
            'name' => 'HQ', 'region' => 'Jakarta', 'type' => 'head_office',
        ]);
        $vehicle = DB::table('vehicles')->insertGetId([
            'plate_number' => 'B 1000 AB', 'type' => 'passenger', 'ownership' => 'company', 'location_id' => $location,
        ]);
        $driver = DB::table('drivers')->insertGetId(['name' => 'Driver']);

        return DB::table('bookings')->insertGetId([
            'code' => 'BK-TEST-1', 'created_by' => $user, 'vehicle_id' => $vehicle, 'driver_id' => $driver,
            'requester_name' => 'Employee', 'destination' => 'Mine', 'purpose' => 'Inspection',
            'start_at' => '2026-10-15 08:00:00+07', 'end_at' => '2026-10-15 17:00:00+07', 'status' => 'pending',
        ]);
    }
}
