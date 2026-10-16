<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingReportController;
use App\Http\Controllers\BookingWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::view('/login', 'app')->name('login');
Route::view('/dashboard', 'app');
Route::view('/bookings/{path?}', 'app')->where('path', '.*');
Route::view('/reports', 'app');
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/api/me', fn (Request $request) => $request->user()->only('id', 'name', 'email', 'role'));
    Route::get('/api/dashboard', DashboardController::class)->middleware('can:admin');
    Route::get('/api/reports/bookings/export', [BookingReportController::class, 'export']);
    Route::get('/api/booking-options', fn () => [
        'vehicles' => Vehicle::with('location')->get(),
        'drivers' => Driver::all(),
        'approvers' => User::where('role', 'approver')->get(['id', 'name']),
    ])->middleware('can:admin');
    Route::get('/api/bookings', [BookingController::class, 'index']);
    Route::get('/api/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/api/bookings', [BookingController::class, 'store'])->middleware('can:admin');
    Route::patch('/api/bookings/{booking}', [BookingController::class, 'update'])->middleware('can:admin');
    Route::post('/api/bookings/{booking}/decision', [BookingWorkflowController::class, 'decision']);
    Route::post('/api/bookings/{booking}/complete', [BookingWorkflowController::class, 'complete']);
});
