<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HasilTes;
use App\Models\TesPsikologis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TesApiController extends Controller
{
    /**
     * Menampilkan daftar tes psikologis yang memiliki pertanyaan (format JSON).
     */
    public function index()
    {
        $daftarTes = TesPsikologis::query()
            ->withCount('pertanyaan')
            ->has('pertanyaan')
            ->orderBy('nama_tes')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List Data Tes Psikologis Berhasil Diambil',
            'data' => $daftarTes
        ], 200);
    }

    /**
     * Menampilkan daftar pertanyaan beserta pilihan jawaban untuk dikerjakan (format JSON).
     * Kolom `poin` sengaja tidak dimuat agar skor tidak bocor ke client.
     */
    public function show($id)
    {
        $tes = TesPsikologis::with([
            'pertanyaan' => fn ($q) => $q->orderBy('id'),
            'pertanyaan.jawabanPsikologis' => fn ($q) => $q
                ->select('id', 'id_pertanyaan', 'jawaban')
                ->orderBy('id'),
        ])->find($id);

        if (!$tes || $tes->pertanyaan->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tes tidak ditemukan atau belum memiliki pertanyaan.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Soal Tes Berhasil Diambil',
            'data' => $tes
        ], 200);
    }

    /**
     * Menyimpan jawaban user, menghitung total poin, dan mengembalikan hasil tes (format JSON).
     */
    public function submit(Request $request, $id)
    {
        $tes = TesPsikologis::find($id);

        if (!$tes) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tes tidak ditemukan.'
            ], 404);
        }

        $request->validate([
            'jawaban' => ['required', 'array'],
        ], [
            'jawaban.required' => 'Jawab semua pertanyaan terlebih dahulu.',
        ]);

        $pertanyaan = $tes->pertanyaan()
            ->with('jawabanPsikologis')
            ->orderBy('id')
            ->get();

        if ($pertanyaan->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pertanyaan tes kosong.'
            ], 404);
        }

        $pilihan = $request->input('jawaban', []);
        $rows = [];
        $total = 0;

        foreach ($pertanyaan as $i => $p) {
            $jawaban = $p->jawabanPsikologis->firstWhere('id', (int) ($pilihan[$p->id] ?? 0));

            if (! $jawaban) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pertanyaan nomor ' . ($i + 1) . ' belum dijawab atau pilihan tidak valid.'
                ], 422);
            }

            $rows[] = [
                'id_pertanyaan' => $p->id,
                'id_jawaban' => $jawaban->id,
                'poin' => $jawaban->poin,
            ];
            $total += $jawaban->poin;
        }

        $hasil = DB::transaction(function () use ($tes, $rows, $total) {
            $hasil = HasilTes::create([
                'id_user' => Auth::id(),
                'id_tes' => $tes->id,
                'total_poin' => $total,
            ]);

            $hasil->jawabanUser()->createMany($rows);

            return $hasil;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Selesai! Terima kasih sudah melangkah maju untuk mengenal dirimu lebih baik.',
            'data' => $hasil->load(['tesPsikologis', 'jawabanUser.pertanyaanPsikologis', 'jawabanUser.jawabanPsikologis'])
        ], 201);
    }

    /**
     * Menampilkan hasil pengerjaan tes berdasarkan ID Hasil Tes (format JSON).
     */
    public function hasil($id)
    {
        $hasil = HasilTes::with([
            'tesPsikologis',
            'jawabanUser.pertanyaanPsikologis',
            'jawabanUser.jawabanPsikologis',
        ])->find($id);

        if (!$hasil || (int) $hasil->id_user !== (int) Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hasil tes tidak ditemukan atau tidak memiliki akses.'
            ], 403);
        }

        $poinMaks = $hasil->tesPsikologis
            ->pertanyaan()
            ->with('jawabanPsikologis')
            ->get()
            ->sum(fn ($p) => (int) $p->jawabanPsikologis->max('poin'));

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil Tes Berhasil Diambil',
            'data' => [
                'hasil_tes' => $hasil,
                'poin_maks' => $poinMaks
            ]
        ], 200);
    }

    /**
     * Menampilkan riwayat pengerjaan tes user yang sedang login (format JSON).
     */
    public function riwayat()
    {
        $riwayat = HasilTes::query()
            ->with('tesPsikologis')
            ->where('id_user', Auth::id())
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat Tes Berhasil Diambil',
            'data' => $riwayat
        ], 200);
    }
}