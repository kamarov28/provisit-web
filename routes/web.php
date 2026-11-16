<?php

use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VisitController::class, 'index'])->name('visits.index');
Route::get('/products/search', [VisitController::class, 'productSearch'])->name('products.search');
Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
