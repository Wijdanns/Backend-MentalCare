<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ArtikelApiController;
use App\Http\Controllers\Api\PsikologApiController;
use App\Http\Controllers\Api\TesApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\KonsultasiApiController;
use App\Http\Controllers\Api\PembayaranApiController;
use App\Http\Controllers\Api\ProfilApiController;

route::middleware(['web'])->group(function() {



// ================= Public Routes =================
Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/register', [AuthApiController::class, 'register']);

// Artikel
Route::get('/artikel', [ArtikelApiController::class, 'index']);
Route::get('/artikel/{id}', [ArtikelApiController::class, 'show']);

// Psikolog
Route::get('/psikolog', [PsikologApiController::class, 'index']);
Route::get('/psikolog/{id}', [PsikologApiController::class, 'show']);

// Tes Psikologi
Route::get('/tes-psikologi', [TesApiController::class, 'index']);


// ================= Protected Routes (Auth Session) =================
Route::middleware('auth')->group(function () {
    
    Route::get('/user', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()
        ]);
    });

    Route::post('/logout', [AuthApiController::class, 'logout']);

    // Profil User
    Route::get('/profil', [ProfilApiController::class, 'index']);
    Route::post('/profil', [ProfilApiController::class, 'update']);

    // Tes Psikologi (Pasien)
    Route::get('/tes-psikologi/riwayat', [TesApiController::class, 'riwayat']);
    Route::get('/tes-psikologi/hasil/{id}', [TesApiController::class, 'hasil']);
    Route::get('/tes-psikologi/{id}', [TesApiController::class, 'show']);
    Route::post('/tes-psikologi/{id}/submit', [TesApiController::class, 'submit']);

    // Konsultasi (Pasien)
    Route::get('/konsultasi', [KonsultasiApiController::class, 'index']);
    Route::get('/konsultasi/riwayat', [KonsultasiApiController::class, 'riwayat']);
    Route::post('/konsultasi', [KonsultasiApiController::class, 'store']);
    Route::get('/konsultasi/{id}', [KonsultasiApiController::class, 'show']);
    Route::post('/konsultasi/{id}/bayar', [KonsultasiApiController::class, 'bayar']);

    // Pembayaran
    Route::post('/konsultasi/{id}/pembayaran/konfirmasi', [PembayaranApiController::class, 'konfirmasi']);
    Route::post('/konsultasi/{id}/pembayaran/gagal', [PembayaranApiController::class, 'gagal']);
});

});