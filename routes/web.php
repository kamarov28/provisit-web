<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

// Guest Authentication (Email & Password Login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Signed Calendar Feed (dapat diakses aplikasi kalender eksternal lewat link bertanda tangan)
Route::get('/visits/calendar.ics', [VisitController::class, 'calendarIcs'])
    ->middleware('signed')
    ->name('visits.calendar.ics');

// Protected PROvisit Application Routes
Route::middleware('auth')->group(function () {
    Route::get('/', [VisitController::class, 'index'])->name('visits.index');
    Route::get('/products/search', [VisitController::class, 'productSearch'])
        ->middleware('throttle:60,1')
        ->name('products.search');
    Route::get('/visits/{visit}/calendar.ics', [VisitController::class, 'visitIcs'])->name('visits.single.ics');

    Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
    Route::put('/visits/{visit}', [VisitController::class, 'update'])->name('visits.update');
    Route::patch('/visits/{visit}/reschedule', [VisitController::class, 'reschedule'])->name('visits.reschedule');
    Route::patch('/visits/{visit}/status', [VisitController::class, 'updateStatus'])->name('visits.status');
    Route::patch('/action-plans/{actionPlan}/toggle', [VisitController::class, 'toggleActionPlan'])->name('action-plans.toggle');

    Route::post('/master/customers', [MasterDataController::class, 'storeCustomer'])->name('master.customers.store');
    Route::post('/master/customer-pics', [MasterDataController::class, 'storeCustomerPic'])->name('master.customer-pics.store');
    Route::post('/master/products', [MasterDataController::class, 'storeProduct'])->name('master.products.store');
});
