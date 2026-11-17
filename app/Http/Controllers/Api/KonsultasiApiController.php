<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KonsultasiResource;
use App\Http\Resources\PsikologResource;
use App\Models\JadwalPsikolog;
use App\Models\Konsultasi;
use App\Models\Psikolog;
use Illuminate\Http\Request;
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
            'status'  => 'success',
            'message' => 'List Psikolog dan Jadwal Berhasil Diambil',
            'data'    => PsikologResource::collection($psikologs),
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
            'id_psikolog.required'       => 'Pilih psikolog terlebih dahulu.',
            'id_psikolog.exists'         => 'Psikolog yang dipilih tidak valid.',
            'id_jadwal.required'         => 'Pilih jadwal konsultasi terlebih dahulu.',
            'id_jadwal.exists'           => 'Jadwal yang dipilih tidak valid.',
            'metode_pembayaran.required' => 'Pilih metode pembayaran terlebih dahulu.',
            'metode_pembayaran.in'       => 'Metode pembayaran tidak valid.',
        ]);

        $psikolog = Psikolog::findOrFail($data['id_psikolog']);

        $konsultasi = DB::transaction(function () use ($data, $psikolog, $request) {
            $jadwal = JadwalPsikolog::where('id', $data['id_jadwal'])
                ->where('id_psikolog', $psikolog->id)
                ->where('status', 'Tersedia')
                ->lockForUpdate()
                ->first();

            if (! $jadwal) {
                return null;
            }

            $konsultasi = Konsultasi::create([
                'id_user'           => $request->user()->id,
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

        if (! $konsultasi) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Jadwal sudah tidak tersedia. Silakan pilih jadwal lain.',
            ], 409);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Pemesanan berhasil! Silakan selesaikan pembayaran.',
            'data'    => new KonsultasiResource($konsultasi->load(['psikolog', 'jadwal', 'pembayaran'])),
        ], 201);
    }

    /**
     * Menampilkan detail konfirmasi pembayaran konsultasi berdasarkan ID.
     */
    public function show(Request $request, Int $id)
    {
        $konsultasi = $this->milikSaya($request, $id, ['psikolog', 'jadwal', 'pembayaran']);

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

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail Konfirmasi Konsultasi',
            'data'    => new KonsultasiResource($konsultasi),
        ], 200);
    }

    /**
     * Mengunggah bukti pembayaran untuk konsultasi tertentu.
     */
    public function bayar(Request $request, Int $id)
    {
        $konsultasi = $this->milikSaya($request, $id, ['pembayaran']);

        if (! $konsultasi) {
            return $this->tidakDitemukan();
        }

        if ($konsultasi->sudahKedaluwarsa()) {
            Konsultasi::batalkanYangKedaluwarsa();

            return response()->json([
                'status'  => 'error',
                'message' => 'Batas waktu pembayaran (1 jam) telah habis. Silakan buat jadwal ulang.',
            ], 400);
        }

        if ($konsultasi->status_konsultasi !== 'Menunggu Pembayaran') {
            return response()->json([
                'status'  => 'error',
                'message' => "Konsultasi berstatus {$konsultasi->status_konsultasi}, tidak dapat menerima bukti pembayaran.",
            ], 409);
        }

        $request->validate([
            'bukti_pembayaran' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:2048'],
        ], [
            'bukti_pembayaran.required' => 'Silakan unggah bukti pembayaran terlebih dahulu.',
            'bukti_pembayaran.mimes'    => 'Format file harus berupa JPG, PNG, atau PDF.',
            'bukti_pembayaran.max'      => 'Ukuran file bukti pembayaran maksimal 2MB.',
        ]);

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

        return response()->json([
            'status'  => 'success',
            'message' => 'Bukti pembayaran berhasil diunggah! Mohon tunggu verifikasi dari admin.',
            'data'    => new KonsultasiResource($konsultasi->load(['psikolog', 'jadwal', 'pembayaran'])),
        ], 200);
    }

    /** Mengunduh file bukti pembayaran — hanya pemilik konsultasi. */
    public function bukti(Request $request, int $id)
    {
        $konsultasi = $this->milikSaya($request, $id, ['pembayaran']);
        $path = $konsultasi?->pembayaran?->bukti_pembayaran;

        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return $this->tidakDitemukan('Bukti pembayaran tidak ditemukan.');
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    /**
     * Menampilkan riwayat konsultasi user yang sedang login.
     */
    public function riwayat(Request $request)
    {
        Konsultasi::batalkanYangKedaluwarsa();

        $konsultasi = Konsultasi::with(['psikolog', 'jadwal', 'pembayaran'])
            ->where('id_user', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Riwayat Konsultasi Berhasil Diambil',
            'data'    => KonsultasiResource::collection($konsultasi),
        ], 200);
    }

    private function milikSaya(Request $request,  int $id, array $with = []): ?Konsultasi
    {
        $konsultasi = Konsultasi::with($with)->find($id);

        return ($konsultasi && (int) $konsultasi->id_user === (int) $request->user()->id)
            ? $konsultasi
            : null;
    }

    private function tidakDitemukan(string $pesan = 'Konsultasi tidak ditemukan.')
    {
        return response()->json(['status' => 'error', 'message' => $pesan], 404);
    }
}