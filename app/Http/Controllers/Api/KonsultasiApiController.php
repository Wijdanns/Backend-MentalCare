<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalPsikolog;
use App\Models\Konsultasi;
use App\Models\Psikolog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KonsultasiApiController extends Controller
{
    /**
     * Menampilkan daftar psikolog beserta jadwal yang tersedia dalam format JSON.
     */
    public function index()
    {
        Konsultasi::batalkanYangKedaluwarsa();

        $psikologs = Psikolog::query()
            ->with(['jadwalTersedia' => function ($q) {
                $q->orderBy('tanggal')->orderBy('jam_mulai');
            }])
            ->get()
            ->each(function ($p) { 
                $p->tersedia = $p->jadwalTersedia->isNotEmpty();
            });

        return response()->json([
            'status' => 'success',
            'message' => 'List Psikolog dan Jadwal Berhasil Diambil',
            'data' => $psikologs
        ], 200);
    }

    /**
     * Membuat pemesanan konsultasi baru (menyimpan transaksi & jadwal).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_psikolog'       => ['required', 'exists:psikolog,id'],
            'id_jadwal'         => ['required', 'exists:jadwal_psikolog,id'],
            'metode_pembayaran' => ['required', 'in:Transfer Bank,E-Wallet'],
        ], [
            'id_psikolog.required'      => 'Pilih psikolog terlebih dahulu.',
            'id_psikolog.exists'        => 'Psikolog yang dipilih tidak valid.',
            'id_jadwal.required'        => 'Pilih jadwal konsultasi terlebih dahulu.',
            'id_jadwal.exists'          => 'Jadwal yang dipilih tidak valid.',
            'metode_pembayaran.required' => 'Pilih metode pembayaran terlebih dahulu.',
            'metode_pembayaran.in'      => 'Metode pembayaran tidak valid.',
        ]);

        $psikolog = Psikolog::findOrFail($data['id_psikolog']);

        $konsultasi = DB::transaction(function () use ($data, $psikolog) {
            $jadwal = JadwalPsikolog::where('id', $data['id_jadwal'])
                ->where('id_psikolog', $psikolog->id)
                ->where('status', 'Tersedia')
                ->lockForUpdate()
                ->firstOrFail();

            $konsultasi = Konsultasi::create([
                'id_user'           => Auth::id(),
                'id_psikolog'       => $psikolog->id,
                'id_jadwal'         => $jadwal->id,
                'status_konsultasi' => 'Menunggu Pembayaran',
                'total_biaya'       => $psikolog->biaya,
            ]);

            $konsultasi->pembayaran()->update([
                'metode_pembayaran' => $data['metode_pembayaran'],
            ]);

            return $konsultasi;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Pemesanan berhasil! Silakan selesaikan pembayaran.',
            'data' => $konsultasi->load(['psikolog', 'jadwal', 'pembayaran'])
        ], 201);
    }

    /**
     * Menampilkan detail konfirmasi pembayaran konsultasi berdasarkan ID.
     */
    public function show($id)
    {
        $konsultasi = Konsultasi::with(['psikolog', 'jadwal', 'pembayaran'])->find($id);

        if (!$konsultasi || $konsultasi->id_user !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Konsultasi tidak ditemukan atau tidak memiliki akses'
            ], 403);
        }

        if ($konsultasi->sudahKedaluwarsa()) {
            Konsultasi::batalkanYangKedaluwarsa();

            return response()->json([
                'status' => 'error',
                'message' => 'Batas waktu pembayaran (1 jam) telah habis. Pesanan dibatalkan otomatis.'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Konfirmasi Konsultasi',
            'data' => $konsultasi
        ], 200);
    }

    /**
     * Mengunggah bukti pembayaran untuk konsultasi tertentu.
     */
    public function bayar(Request $request, $id)
    {
        $konsultasi = Konsultasi::with('pembayaran')->find($id);

        if (!$konsultasi || $konsultasi->id_user !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Konsultasi tidak ditemukan atau tidak memiliki akses'
            ], 403);
        }

        if ($konsultasi->sudahKedaluwarsa()) {
            Konsultasi::batalkanYangKedaluwarsa();

            return response()->json([
                'status' => 'error',
                'message' => 'Batas waktu pembayaran (1 jam) telah habis. Silakan buat jadwal ulang.'
            ], 400);
        }

        $request->validate([
            'bukti_pembayaran' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:2048'],
        ], [
            'bukti_pembayaran.required' => 'Silakan unggah bukti pembayaran terlebih dahulu.',
            'bukti_pembayaran.mimes'    => 'Format file harus berupa JPG, PNG, atau PDF.',
            'bukti_pembayaran.max'      => 'Ukuran file bukti pembayaran maksimal 2MB.',
        ]);

        if ($request->hasFile('bukti_pembayaran')) {
            $file = $request->file('bukti_pembayaran');
            $filename = time() . '_' . $konsultasi->id . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('bukti_pembayaran', $filename, 'public');

            $pembayaran = $konsultasi->pembayaran;
            if ($pembayaran->bukti_pembayaran && Storage::disk('public')->exists($pembayaran->bukti_pembayaran)) {
                Storage::disk('public')->delete($pembayaran->bukti_pembayaran);
            }

            $pembayaran->update([
                'bukti_pembayaran'  => $path,
                'status_pembayaran' => 'Pending',
                'waktu_pembayaran'  => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bukti pembayaran berhasil diunggah! Mohon tunggu verifikasi dari admin.',
            'data' => $konsultasi->load('pembayaran')
        ], 200);
    }

    /**
     * Menampilkan riwayat konsultasi user yang sedang login.
     */
    public function riwayat()
    {
        Konsultasi::batalkanYangKedaluwarsa();

        $konsultasi = Konsultasi::with(['psikolog', 'jadwal', 'pembayaran'])
            ->where('id_user', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat Konsultasi Berhasil Diambil',
            'data' => $konsultasi
        ], 200);
    }
}