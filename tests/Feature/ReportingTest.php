<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_completed_month_in_wib(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T00:00:00+07:00'));
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret', 'role' => 'admin']);
        $location = Location::create(['name' => 'Site', 'region' => 'Sulawesi', 'type' => 'mine']);
        $vehicle = Vehicle::create(['plate_number' => 'B 1001 AA', 'type' => 'passenger', 'ownership' => 'company', 'location_id' => $location->id]);
        $driver = Driver::create(['name' => 'Budi']);

        $base = [
            'created_by' => $admin->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
            'requester_name' => 'Employee', 'destination' => 'Mine 1', 'purpose' => 'Inspection',
            'start_at' => '2026-10-20 01:00:00+00', 'end_at' => '2026-10-20 10:00:00+00',
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('bookings')->insert([
            $base + ['code' => 'BK-APPROVED', 'status' => 'approved', 'completed_at' => null],
            $base + ['code' => 'BK-COMPLETED', 'status' => 'completed', 'completed_at' => '2026-10-31 17:15:00+00'],
        ]);

        $this->actingAs($admin)->getJson('/api/dashboard')
            ->assertOk()->assertJsonPath('year', 2026)->assertJsonPath('total_bookings', 2)
            ->assertJsonPath('approved', 1)->assertJsonPath('completed_trips', 1)
            ->assertJsonPath('monthly_usage.9.trips', 0)
            ->assertJsonPath('monthly_usage.10.trips', 1);
    }

    public function test_report_period_is_half_open_wib(): void
    {
        [$admin, $vehicleId, $driverId] = $this->reportFixture();
        $base = [
            'created_by' => $admin->id, 'vehicle_id' => $vehicleId, 'driver_id' => $driverId,
            'requester_name' => 'Employee', 'destination' => 'Mine', 'purpose' => 'Inspection',
            'start_at' => '2026-10-20 01:00:00+00', 'end_at' => '2026-10-20 10:00:00+00',
            'completed_at' => null, 'updated_at' => now(),
        ];
        DB::table('bookings')->insert([
            $base + ['code' => 'BK-START', 'status' => 'approved', 'created_at' => '2026-09-30 17:00:00+00'],
            $base + ['code' => 'BK-BEFORE', 'status' => 'approved', 'created_at' => '2026-09-30 16:59:59+00'],
            $base + ['code' => 'BK-END', 'status' => 'approved', 'created_at' => '2026-10-31 17:00:00+00'],
            $base + ['code' => 'BK-PENDING', 'status' => 'pending', 'created_at' => '2026-10-15 00:00:00+00'],
        ]);

        $this->actingAs($admin)->getJson('/api/bookings?from=2026-10-01&to=2026-10-31&status=approved')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'BK-START');
        $this->getJson('/api/bookings?from=2026-10-31&to=2026-10-01')->assertUnprocessable();
    }

    public function test_export_matches_filtered_list(): void
    {
        [$admin, $vehicleId, $driverId] = $this->reportFixture();
        DB::table('bookings')->insert([
            'code' => 'BK-EXPORT', 'created_by' => $admin->id, 'vehicle_id' => $vehicleId, 'driver_id' => $driverId,
            'requester_name' => '=1+1', 'destination' => 'Mine 5', 'purpose' => 'Inspection',
            'start_at' => '2026-10-20 01:00:00+00', 'end_at' => '2026-10-20 10:00:00+00',
            'status' => 'approved', 'completed_at' => null, 'created_at' => '2026-10-15 00:00:00+00', 'updated_at' => now(),
        ]);
        $filters = '?from=2026-10-01&to=2026-10-31&status=approved';
        $this->actingAs($admin)->getJson('/api/bookings'.$filters)->assertOk()->assertJsonCount(1, 'data');
        $response = $this->get('/api/reports/bookings/export'.$filters)->assertOk()->assertDownload('booking-report.xlsx');
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));

        $path = tempnam(sys_get_temp_dir(), 'fleetops-xlsx-');
        file_put_contents($path, $response->streamedContent());
        $zip = new \ZipArchive;
        $this->assertSame(true, $zip->open($path));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml').$zip->getFromName('xl/sharedStrings.xml');
        $this->assertStringContainsString('BK-EXPORT', $sheet);
        $zip->close();
        $spreadsheet = IOFactory::load($path);
        $this->assertSame('=1+1', $spreadsheet->getActiveSheet()->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $spreadsheet->getActiveSheet()->getCell('B2')->getDataType());
        $spreadsheet->disconnectWorksheets();
        unlink($path);
    }

    public function test_export_matches_booking_list_for_full_filter_set(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T12:00:00+07:00'));
        [$admin, $vehicleId, $driverId] = $this->reportFixture();
        $firstApprover = User::create(['name' => 'First', 'email' => 'first@example.test', 'password' => 'secret', 'role' => 'approver']);
        $secondApprover = User::create(['name' => 'Second', 'email' => 'second@example.test', 'password' => 'secret', 'role' => 'approver']);
        $otherLocation = Location::create(['name' => 'Other Site', 'region' => 'Sulawesi', 'type' => 'mine']);
        $otherVehicle = Vehicle::create(['plate_number' => 'B 2002 AA', 'type' => 'goods', 'ownership' => 'rental', 'location_id' => $otherLocation->id]);
        $otherDriver = Driver::create(['name' => 'Andi']);

        $base = [
            'created_by' => $admin->id, 'requester_name' => 'Employee', 'purpose' => 'Inspection',
            'start_at' => '2026-10-20 01:00:00+00', 'end_at' => '2026-10-20 10:00:00+00',
            'status' => 'approved', 'completed_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
        $targetId = DB::table('bookings')->insertGetId($base + [
            'code' => 'BK-FILTER-TARGET', 'vehicle_id' => $vehicleId, 'driver_id' => $driverId, 'destination' => 'Mine Target',
        ]);
        $otherId = DB::table('bookings')->insertGetId($base + [
            'code' => 'BK-FILTER-OTHER', 'vehicle_id' => $otherVehicle->id, 'driver_id' => $otherDriver->id, 'destination' => 'Mine Other',
        ]);
        foreach ([[$targetId, $firstApprover->id], [$otherId, $secondApprover->id]] as [$bookingId, $approverId]) {
            DB::table('booking_approvals')->insert([
                'booking_id' => $bookingId, 'approver_id' => $approverId, 'level' => 1, 'status' => 'pending',
                'decided_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $filters = http_build_query([
            'search' => 'mine', 'status' => 'approved', 'from' => '2026-10-16', 'to' => '2026-10-16',
            'vehicle_id' => $vehicleId, 'driver_id' => $driverId, 'approver_id' => $firstApprover->id,
        ]);
        $this->actingAs($admin)->getJson('/api/bookings?'.$filters)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $targetId);
        $response = $this->get('/api/reports/bookings/export?'.$filters)->assertOk()->assertDownload('booking-report.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'fleetops-full-filter-');
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        $codes = array_column($spreadsheet->getActiveSheet()->toArray(null, true, true, false), 0);
        $this->assertSame(['Booking Code', 'BK-FILTER-TARGET'], $codes);
        $spreadsheet->disconnectWorksheets();
        unlink($path);
    }

    public function test_report_export_is_admin_only(): void
    {
        $approver = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'secret', 'role' => 'approver']);

        $this->actingAs($approver)->get('/api/reports/bookings/export')->assertForbidden();
    }

    private function reportFixture(): array
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret', 'role' => 'admin']);
        $location = Location::create(['name' => 'Site', 'region' => 'Sulawesi', 'type' => 'mine']);
        $vehicle = Vehicle::create(['plate_number' => 'B 1001 AA', 'type' => 'passenger', 'ownership' => 'company', 'location_id' => $location->id]);
        $driver = Driver::create(['name' => 'Budi']);

        return [$admin, $vehicle->id, $driver->id];
    }
}
