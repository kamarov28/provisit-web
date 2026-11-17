<?php

use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VisitController::class, 'index'])->name('visits.index');
Route::get('/products/search', [VisitController::class, 'productSearch'])->name('products.search');
Route::get('/visits/calendar.ics', [VisitController::class, 'calendarIcs'])->name('visits.calendar.ics');
Route::get('/visits/{visit}/calendar.ics', [VisitController::class, 'visitIcs'])->name('visits.single.ics');
Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
Route::put('/visits/{visit}', [VisitController::class, 'update'])->name('visits.update');
Route::patch('/visits/{visit}/reschedule', [VisitController::class, 'reschedule'])->name('visits.reschedule');
Route::patch('/visits/{visit}/status', [VisitController::class, 'updateStatus'])->name('visits.status');