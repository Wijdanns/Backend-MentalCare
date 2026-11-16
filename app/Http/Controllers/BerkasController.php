<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\Request;

/**
 * Melayani file dari storage/app/public tanpa `php artisan storage:link`.
 */
class BerkasController extends Controller
{
    public function fotoPsikolog(string $file)
    {
        return $this->kirim('psikolog', $file);
    }

    public function fotoArtikel(string $file)
    {
        return $this->kirim('artikel', $file);
    }

    /** Bukti pembayaran bersifat privat: admin, atau pasien pemilik konsultasi. */
    public function buktiPembayaran(Request $request, string $file)
    {
        $nama = basename($file);

        $pembayaran = Pembayaran::with('konsultasi')
            ->where('bukti_pembayaran', 'bukti_pembayaran/' . $nama)
            ->first();

        abort_unless($pembayaran, 404);

        $user = $request->user();
        $boleh = $user->role === 'admin'
            || (int) $pembayaran->konsultasi?->id_user === (int) $user->id;

        abort_unless($boleh, 403);

        return $this->kirim('bukti_pembayaran', $nama);
    }

    private function kirim(string $folder, string $file)
    {
        $path = storage_path("app/public/{$folder}/" . basename($file));

        abort_unless(is_file($path), 404);

        return response()->file($path);
    }
}
