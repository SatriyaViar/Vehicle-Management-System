<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingLog;
use App\Models\Driver;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(['email' => 'admin@fleetops.test'], [
            'name' => 'Admin Pool', 'password' => 'demo12345', 'role' => 'admin',
        ]);
        $first = User::firstOrCreate(['email' => 'manager@fleetops.test'], [
            'name' => 'Manager Operasional', 'password' => 'demo12345', 'role' => 'approver',
        ]);
        $second = User::firstOrCreate(['email' => 'head@fleetops.test'], [
            'name' => 'Head Operasional', 'password' => 'demo12345', 'role' => 'approver',
        ]);

        $sites = [
            ['Kantor Pusat', 'Jakarta', 'head_office'],
            ['Kantor Cabang', 'Sulawesi Tenggara', 'branch'],
            ['Tambang Kolaka', 'Sulawesi Tenggara', 'mine'],
            ['Tambang Konawe', 'Sulawesi Tenggara', 'mine'],
            ['Tambang Morowali', 'Sulawesi Tengah', 'mine'],
            ['Tambang Halmahera', 'Maluku Utara', 'mine'],
            ['Tambang Sorowako', 'Sulawesi Selatan', 'mine'],
            ['Tambang Pomalaa', 'Sulawesi Tenggara', 'mine'],
        ];
        $locations = [];
        foreach ($sites as [$name, $region, $type]) {
            $locations[] = Location::firstOrCreate(['name' => $name], ['region' => $region, 'type' => $type]);
        }

        $vehicles = [];
        foreach ([
            ['B 1001 NK', 'passenger', 'company'],
            ['B 1002 NK', 'goods', 'company'],
            ['DT 2001 NK', 'passenger', 'rental'],
            ['DT 2002 NK', 'goods', 'rental'],
        ] as $i => [$plate, $type, $ownership]) {
            $vehicles[] = Vehicle::firstOrCreate(['plate_number' => $plate], [
                'type' => $type, 'ownership' => $ownership, 'location_id' => $locations[$i + 2]->id,
            ]);
        }

        $drivers = [];
        foreach (['Budi Santoso', 'Andi Pratama', 'Rudi Hartono', 'Fajar Putra'] as $i => $name) {
            $drivers[] = Driver::firstOrCreate(['name' => $name], ['phone' => '08120000100'.$i]);
        }

        if (Booking::exists()) {
            return;
        }

        $now = now('Asia/Jakarta');
        $starts = [
            $now->copy()->startOfMonth()->addDays(2)->setTime(8, 0),
            $now->copy()->addDays(2)->setTime(8, 0),
            $now->copy()->addDays(3)->setTime(8, 0),
            $now->copy()->addDays(4)->setTime(8, 0),
        ];
        foreach (['completed', 'approved', 'pending', 'rejected'] as $i => $status) {
            $start = $starts[$i];
            $booking = new Booking([
                'code' => 'BK-'.$start->format('Ymd').'-'.sprintf('%03d', $i + 1),
                'created_by' => $admin->id,
                'vehicle_id' => $vehicles[$i]->id,
                'driver_id' => $drivers[$i]->id,
                'requester_name' => ['Satriya', 'Mira', 'Rina', 'Hadi'][$i],
                'destination' => $locations[$i + 2]->name,
                'purpose' => 'Kunjungan operasional',
                'start_at' => $start,
                'end_at' => $start->copy()->addHours(6),
                'status' => $status,
                'completed_at' => $status === 'completed' ? $start->copy()->addHours(6) : null,
            ]);
            $booking->created_at = $status === 'completed' ? $start->copy()->subDay() : $now;
            $booking->save();

            foreach ([1 => $first, 2 => $second] as $level => $approver) {
                $decision = match (true) {
                    $status === 'completed', $status === 'approved' => 'approved',
                    $status === 'rejected' && $level === 1 => 'rejected',
                    default => 'pending',
                };
                BookingApproval::create([
                    'booking_id' => $booking->id, 'approver_id' => $approver->id, 'level' => $level,
                    'status' => $decision,
                    'decided_at' => $decision === 'pending' ? null : $start->copy()->subHours(3 - $level),
                ]);
            }

            $actions = ['BOOKING_CREATED'];
            if (in_array($status, ['completed', 'approved'])) {
                array_push($actions, 'APPROVAL_LEVEL_1_APPROVED', 'APPROVAL_LEVEL_2_APPROVED');
            } elseif ($status === 'rejected') {
                $actions[] = 'BOOKING_REJECTED';
            }
            if ($status === 'completed') {
                $actions[] = 'BOOKING_COMPLETED';
            }
            foreach ($actions as $action) {
                $actor = match ($action) {
                    'APPROVAL_LEVEL_1_APPROVED', 'BOOKING_REJECTED' => $first,
                    'APPROVAL_LEVEL_2_APPROVED' => $second,
                    default => $admin,
                };
                BookingLog::create(['booking_id' => $booking->id, 'actor_id' => $actor->id, 'action' => $action]);
            }
        }
    }
}
