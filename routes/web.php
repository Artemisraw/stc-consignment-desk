<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ConsignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\TripController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/', TrackingController::class)->name('tracking');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

// auth.session signs out other sessions once a password changes (own change or owner reset).
Route::middleware(['auth', 'auth.session', EnsureUserIsActive::class])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    Route::patch('/staff/{user}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle');

    Route::get('/consignments', [ConsignmentController::class, 'index'])->name('consignments.index');
    Route::get('/consignments/create', [ConsignmentController::class, 'create'])->name('consignments.create');
    Route::post('/consignments', [ConsignmentController::class, 'store'])->name('consignments.store');
    Route::get('/consignments/{consignment}', [ConsignmentController::class, 'show'])->name('consignments.show');
    Route::post('/consignments/{consignment}/payments', [ConsignmentController::class, 'payment'])->name('consignments.payment');
    Route::get('/consignments/{consignment}/edit', [ConsignmentController::class, 'edit'])->name('consignments.edit');
    Route::put('/consignments/{consignment}', [ConsignmentController::class, 'update'])->name('consignments.update');
    Route::post('/consignments/{consignment}/cancel', [ConsignmentController::class, 'cancel'])->name('consignments.cancel');
    Route::post('/consignments/{consignment}/payments/{payment}/reverse', [ConsignmentController::class, 'reversePayment'])
        ->scopeBindings()->name('consignments.payments.reverse');
    Route::post('/consignments/{consignment}/deliver', [ConsignmentController::class, 'deliver'])->name('consignments.deliver');

    Route::get('/fleet', [FleetController::class, 'index'])->name('fleet.index');
    Route::post('/fleet/vehicles', [FleetController::class, 'storeVehicle'])->name('fleet.vehicles.store');
    Route::get('/fleet/vehicles/{vehicle}/edit', [FleetController::class, 'editVehicle'])->name('fleet.vehicles.edit');
    Route::put('/fleet/vehicles/{vehicle}', [FleetController::class, 'updateVehicle'])->name('fleet.vehicles.update');
    Route::post('/fleet/drivers', [FleetController::class, 'storeDriver'])->name('fleet.drivers.store');
    Route::get('/fleet/drivers/{driver}/edit', [FleetController::class, 'editDriver'])->name('fleet.drivers.edit');
    Route::put('/fleet/drivers/{driver}', [FleetController::class, 'updateDriver'])->name('fleet.drivers.update');
    Route::patch('/fleet/drivers/{driver}/toggle', [FleetController::class, 'toggleDriver'])->name('fleet.drivers.toggle');
    Route::patch('/fleet/vehicles/{vehicle}/toggle', [FleetController::class, 'toggleVehicle'])->name('fleet.vehicles.toggle');

    Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
    Route::post('/trips', [TripController::class, 'store'])->name('trips.store');
    Route::get('/trips/{trip}/manifest', [TripController::class, 'manifest'])->name('trips.manifest');
    Route::post('/trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');
    Route::post('/trips/{trip}/advance', [TripController::class, 'advance'])->name('trips.advance');

    Route::get('/reports', ReportController::class)->middleware('role:manager,owner')->name('reports');
});
