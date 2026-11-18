<?php

use App\Http\Controllers\ArtikelController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KonsultasiController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\PsikologController;
use App\Http\Controllers\TesController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Web MentalCare
|--------------------------------------------------------------------------
| 1. Publik            : bisa diakses siapa saja
| 2. Auth              : login / register / logout
| 3. Berkas            : foto & bukti pembayaran
| 4. Area pasien       : wajib login sebagai pasien
| 5. Area admin        : wajib login sebagai admin
|
| Panel admin (/admin) dan panel psikolog (/panel-psikolog) dikelola Filament.
| Jadwal pembatalan otomatis ada di routes/console.php.
*/

// ======================= 1. PUBLIK =======================
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/psikolog', [PsikologController::class, 'index'])->name('psikolog.index');
Route::get('/psikolog/{psikolog}', [PsikologController::class, 'show'])->name('psikolog.show');

Route::get('/artikel', [ArtikelController::class, 'index'])->name('artikel.index');
Route::get('/artikel/{artikel}', [ArtikelController::class, 'show'])->name('artikel.show');
Route::get('/artikel/{artikel}/unduh', [ArtikelController::class, 'unduh'])->name('artikel.unduh');

Route::get('/tes-psikologi', [TesController::class, 'index'])->name('tes.index');

Route::get('/konsultasi', [KonsultasiController::class, 'index'])->name('konsultasi.index');
Route::get('/konsultasi/go-login', function () {
    session(['url.intended' => route('konsultasi.index')]);

    return redirect()->route('login');
})->name('konsultasi.go-login');

// ======================= 2. AUTH =======================
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ======================= 3. BERKAS =======================
// Foto publik (dilayani langsung dari storage/app/public, tanpa storage:link)
Route::get('/foto/psikolog/{file}', [BerkasController::class, 'fotoPsikolog'])->name('foto-psikolog');
Route::get('/foto/artikel/{file}', [BerkasController::class, 'fotoArtikel'])->name('foto-artikel');

// Bukti pembayaran: hanya admin atau pasien pemilik konsultasi
Route::get('/bukti-pembayaran/{file}', [BerkasController::class, 'buktiPembayaran'])
    ->middleware('auth')
    ->name('bukti-pembayaran');

// ======================= 4. AREA PASIEN =======================
Route::middleware(['auth', EnsureRole::class . ':pasien'])->group(function () {

    // Tes psikologi (urutan penting: /riwayat & /hasil sebelum /{tes})
    Route::prefix('tes-psikologi')->name('tes.')->group(function () {
        Route::get('/riwayattes', [TesController::class, 'riwayat'])->name('riwayat');
        Route::get('/hasil/{hasil}', [TesController::class, 'hasil'])->name('hasil');
        Route::get('/{tes}', [TesController::class, 'show'])->name('show');
        Route::post('/{tes}', [TesController::class, 'submit'])->name('submit');
    });

    // Akun / profil saya
    Route::get('/profil', [ProfilController::class, 'index'])->name('profil.index');
    Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');

    // Konsultasi
    Route::get('/konsultasi/riwayat', [KonsultasiController::class, 'riwayat'])->name('konsultasi.riwayat');
    Route::post('/konsultasi', [KonsultasiController::class, 'store'])->name('konsultasi.store');
    Route::get('/konsultasi/konfirmasi/{konsultasi}', [KonsultasiController::class, 'konfirmasi'])->name('konsultasi.konfirmasi');
    Route::post('/konsultasi/{konsultasi}/bayar', [KonsultasiController::class, 'bayar'])->name('konsultasi.bayar');
    Route::post('/konsultasi/{konsultasi}/pembayaran/konfirmasi', [PembayaranController::class, 'konfirmasi'])->name('pembayaran.konfirmasi');
});

// ======================= 5. AREA ADMIN =======================
// Dipakai tombol "Export" di panel Filament (sesi login yang sama).
Route::middleware(['auth', EnsureRole::class . ':admin'])->group(function () {
    Route::get('/konsultasi/export', [KonsultasiController::class, 'exportExcel'])->name('konsultasi.export');
});