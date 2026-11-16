<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Konsultasi;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PembayaranApiController extends Controller
{
    /**
     * Konfirmasi pembayaran ("Saya sudah bayar") dalam format JSON.
     */
    public function konfirmasi(Request $request, $id)
    {
        $konsultasi = Konsultasi::find($id);

        if (!$konsultasi || (int) $konsultasi->id_user !== (int) Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Konsultasi tidak ditemukan atau tidak memiliki akses.'
            ], 403);
        }

        DB::transaction(function () use ($konsultasi) {
            $pembayaran = Pembayaran::firstOrCreate(
                ['id_konsultasi' => $konsultasi->id],
                ['status_pembayaran' => 'Pending']
            );

            $pembayaran->update([
                'status_pembayaran' => 'Berhasil',
                'waktu_pembayaran'  => now(),
            ]);

            $konsultasi->update([
                'status_konsultasi' => 'Dikonfirmasi',
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Konfirmasi pembayaran berhasil! Sesi konselingmu siap dijalankan.',
            'data' => $konsultasi->load(['psikolog', 'jadwal', 'pembayaran'])
        ], 200);
    }

    /**
     * Menandai pembayaran gagal dalam format JSON.
     */
    public function gagal(Request $request, $id)
    {
        $konsultasi = Konsultasi::find($id);

        if (!$konsultasi || (int) $konsultasi->id_user !== (int) Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Konsultasi tidak ditemukan atau tidak memiliki akses.'
            ], 403);
        }

        $pembayaran = Pembayaran::where('id_konsultasi', $konsultasi->id)->first();

        if (!$pembayaran) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pembayaran tidak ditemukan.'
            ], 404);
        }

        $pembayaran->update(['status_pembayaran' => 'Gagal']);

        return response()->json([
            'status' => 'success',
            'message' => 'Pembayaran ditandai gagal.',
            'data' => $pembayaran
        ], 200);
    }
}