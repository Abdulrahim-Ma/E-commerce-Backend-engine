<?php

use App\Http\Controllers\InventoryLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/inventory-logs', [InventoryLogController::class, 'index']);
