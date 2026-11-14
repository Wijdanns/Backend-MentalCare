<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArtikelController;
use App\Http\Controllers\TesController;


// Rute untuk mengecek user yang sedang login via API (opsional)
Route::get('/user', function (Request $request) {
    return $request->user();
});

// 1. Rute API untuk Artikel (Metode GET)
Route::get('/artikel', [ArtikelController::class, 'index']);
Route::get('/artikel/{artikel}', [ArtikelController::class, 'show']);

// 2. Rute API untuk Tes Psikologi (Metode GET & POST)
Route::get('/tes-psikologi', [TesController::class, 'index']);