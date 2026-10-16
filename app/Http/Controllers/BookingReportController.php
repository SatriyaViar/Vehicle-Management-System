<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingFilterRequest;
use App\Models\Booking;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingReportController extends Controller
{
    public function export(BookingFilterRequest $request): StreamedResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $filters = $request->validated();
        $bookings = Booking::with(['vehicle', 'driver', 'approvals.approver'])
            ->filtered($filters['from'] ?? null, $filters['to'] ?? null, $filters['status'] ?? null)
            ->latest()->get();
        $rows = [[
            'Booking Code', 'Requester', 'Vehicle', 'Driver', 'Destination', 'Start (WIB)', 'End (WIB)',
            'Created At (WIB)', 'Approver L1', 'Decision L1', 'Approver L2', 'Decision L2', 'Status',
        ]];

        foreach ($bookings as $booking) {
            $approvals = $booking->approvals->keyBy('level');
            $rows[] = [
                $booking->code,
                $booking->requester_name,
                $booking->vehicle?->plate_number ?? '',
                $booking->driver?->name ?? '',
                $booking->destination,
                $booking->start_at->timezone('Asia/Jakarta')->format('Y-m-d H:i'),
                $booking->end_at->timezone('Asia/Jakarta')->format('Y-m-d H:i'),
                $booking->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i'),
                $approvals->get(1)?->approver?->name ?? '',
                $approvals->get(1)?->status ?? '',
                $approvals->get(2)?->approver?->name ?? '',
                $approvals->get(2)?->status ?? '',
                $booking->status,
            ];
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueExplicit([$columnIndex + 1, $rowIndex + 1], (string) $value, DataType::TYPE_STRING);
            }
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'booking-report.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
