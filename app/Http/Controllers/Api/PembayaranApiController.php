<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KonsultasiResource;
use App\Models\Konsultasi;
use Illuminate\Http\Request;

class PembayaranApiController extends Controller
{
    /**
     * "Saya sudah bayar".
     *
     * Pasien TIDAK BISA lagi menandai pembayaran Berhasil sendiri.
     * Endpoint ini hanya memvalidasi bahwa bukti sudah diunggah; status
     * Berhasil/Dikonfirmasi hanya diubah admin (Filament → "Tandai Berhasil").
     */
    public function konfirmasi(Request $request, int $id)
    {
        $konsultasi = $this->milikSaya($request, $id);

        if (! $konsultasi) {
            return $this->tidakDitemukan();
        }

        if ($konsultasi->sudahKedaluwarsa()) {
            Konsultasi::batalkanYangKedaluwarsa();

            return response()->json([
                'status'  => 'error',
                'message' => 'Batas waktu pembayaran (1 jam) telah habis. Pesanan dibatalkan otomatis.',
            ], 400);
        }

        if ($konsultasi->status_konsultasi !== 'Menunggu Pembayaran') {
            return response()->json([
                'status'  => 'error',
                'message' => "Konsultasi berstatus {$konsultasi->status_konsultasi}, tidak dapat dikonfirmasi lagi.",
            ], 409);
        }

        if (blank($konsultasi->pembayaran?->bukti_pembayaran)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unggah bukti pembayaran terlebih dahulu.',
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Bukti pembayaran diterima. Mohon tunggu verifikasi dari admin.',
            'data'    => new KonsultasiResource($konsultasi),
        ], 200);
    }

    /** Menandai pembayaran gagal — hanya selama belum diverifikasi admin. */
    public function gagal(Request $request, Int $id)
    {
        $konsultasi = $this->milikSaya($request, $id);

        if (! $konsultasi) {
            return $this->tidakDitemukan();
        }

        $pembayaran = $konsultasi->pembayaran;

        if (! $pembayaran) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data pembayaran tidak ditemukan.',
            ], 404);
        }

        if ($konsultasi->status_konsultasi !== 'Menunggu Pembayaran'
            || $pembayaran->status_pembayaran === 'Berhasil') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pembayaran ini sudah diproses dan tidak dapat ditandai gagal.',
            ], 409);
        }

        $pembayaran->update(['status_pembayaran' => 'Gagal']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pembayaran ditandai gagal.',
            'data'    => new KonsultasiResource($konsultasi->fresh(['psikolog', 'jadwal', 'pembayaran'])),
        ], 200);
    }

    private function milikSaya(Request $request, Int $id): ?Konsultasi
    {
        $konsultasi = Konsultasi::with(['pembayaran', 'psikolog', 'jadwal'])->find($id);

        return ($konsultasi && (int) $konsultasi->id_user === (int) $request->user()->id)
            ? $konsultasi
            : null;
    }

    private function tidakDitemukan()
    {
        return response()->json([
            'status'  => 'error',
            'message' => 'Konsultasi tidak ditemukan.',
        ], 404);
    }
}
