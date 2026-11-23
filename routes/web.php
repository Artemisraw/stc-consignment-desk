<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ConsignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::get('/', TrackingController::class)->name('tracking');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/consignments', [ConsignmentController::class, 'index'])->name('consignments.index');
    Route::get('/consignments/create', [ConsignmentController::class, 'create'])->name('consignments.create');
    Route::post('/consignments', [ConsignmentController::class, 'store'])->name('consignments.store');
    Route::get('/consignments/{consignment}', [ConsignmentController::class, 'show'])->name('consignments.show');
    Route::post('/consignments/{consignment}/payments', [ConsignmentController::class, 'payment'])->name('consignments.payment');
    Route::post('/consignments/{consignment}/deliver', [ConsignmentController::class, 'deliver'])->name('consignments.deliver');

    Route::get('/fleet', [FleetController::class, 'index'])->name('fleet.index');
    Route::post('/fleet/vehicles', [FleetController::class, 'storeVehicle'])->name('fleet.vehicles.store');
    Route::post('/fleet/drivers', [FleetController::class, 'storeDriver'])->name('fleet.drivers.store');
    Route::patch('/fleet/vehicles/{vehicle}/toggle', [FleetController::class, 'toggleVehicle'])->name('fleet.vehicles.toggle');

    Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
    Route::post('/trips', [TripController::class, 'store'])->name('trips.store');
    Route::get('/trips/{trip}/manifest', [TripController::class, 'manifest'])->name('trips.manifest');
    Route::post('/trips/{trip}/advance', [TripController::class, 'advance'])->name('trips.advance');

    Route::get('/reports', ReportController::class)->middleware('role:manager,owner')->name('reports');
});
