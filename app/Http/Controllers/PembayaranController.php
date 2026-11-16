<?php

namespace App\Http\Controllers;

use App\Models\Konsultasi;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PembayaranController extends Controller
{
    /**
     * Pasien konfirmasi "Saya sudah bayar".
     * Tidak lagi menandai Berhasil — verifikasi hanya oleh admin (Filament).
     */
    public function konfirmasi(Request $request, Konsultasi $konsultasi)
    {
        abort_unless((int) $konsultasi->id_user === (int) Auth::id(), 403);

        $konsultasi->load('pembayaran');

        if ($konsultasi->status_konsultasi !== 'Menunggu Pembayaran') {
            return back()->with('error', 'Konsultasi ini tidak dapat dikonfirmasi lagi.');
        }

        if (blank($konsultasi->pembayaran?->bukti_pembayaran)) {
            return back()->with('error', 'Unggah bukti pembayaran terlebih dahulu.');
        }

        toast('Bukti pembayaran diterima. Mohon tunggu verifikasi dari admin.', 'info');

        return redirect()
            ->route('konsultasi.konfirmasi', $konsultasi)
            ->with('success', 'Bukti pembayaran diterima, menunggu verifikasi admin.');
    }

    /** Menandai pembayaran gagal — hanya selama belum diverifikasi admin. */
    public function gagal(Request $request, Konsultasi $konsultasi)
    {
        abort_unless((int) $konsultasi->id_user === (int) Auth::id(), 403);

        $pembayaran = Pembayaran::where('id_konsultasi', $konsultasi->id)->firstOrFail();

        if ($konsultasi->status_konsultasi !== 'Menunggu Pembayaran'
            || $pembayaran->status_pembayaran === 'Berhasil') {
            return back()->with('error', 'Pembayaran ini sudah diproses dan tidak dapat ditandai gagal.');
        }

        $pembayaran->update(['status_pembayaran' => 'Gagal']);

        toast('Pembayaran gagal dikonfirmasi. Yuk, periksa kembali transaksimu dan coba lagi.', 'error');

        return back()->with('error', 'Pembayaran ditandai gagal.');
    }
}
