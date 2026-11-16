<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Psikolog;
use Illuminate\Http\Request;

class PsikologApiController extends Controller
{
    /**
     * Menampilkan daftar psikolog (mendukung pencarian, filter spesialis, dan ketersediaan) dalam format JSON.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $spesialis = (string) $request->query('spesialis', '');

        $psikologs = Psikolog::query()
            ->withExists('jadwalTersedia as tersedia')
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('nama', 'like', "%{$q}%")
                  ->orWhere('spesialis', 'like', "%{$q}%");
            }))
            ->when($spesialis !== '', fn ($query) => $query->where('spesialis', $spesialis))
            ->when($request->boolean('tersedia'), fn ($query) => $query->has('jadwalTersedia'))
            ->orderByDesc('tersedia')
            ->orderBy('nama')
            ->paginate(9);

        $daftarSpesialis = Psikolog::query()
            ->select('spesialis')
            ->distinct()
            ->orderBy('spesialis')
            ->pluck('spesialis');

        return response()->json([
            'status' => 'success',
            'message' => 'List Data Psikolog Berhasil Diambil',
            'data' => [
                'psikologs' => $psikologs,
                'daftar_spesialis' => $daftarSpesialis
            ]
        ], 200);
    }

    /**
     * Menampilkan detail satu psikolog beserta jadwal yang masih tersedia dalam format JSON.
     */
    public function show($id)
    {
        $psikolog = Psikolog::withExists('jadwalTersedia as tersedia')->find($id);

        if (!$psikolog) {
            return response()->json([
                'status' => 'error',
                'message' => 'Psikolog tidak ditemukan'
            ], 404);
        }

        // Dikelompokkan per tanggal: ['2026-10-14' => [slot, slot], ...]
        $jadwalPerTanggal = $psikolog->jadwalTersedia()
            ->get()
            ->groupBy(fn ($jadwal) => $jadwal->tanggal->toDateString());

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Psikolog Berhasil Diambil',
            'data' => [
                'psikolog' => $psikolog,
                'jadwal_per_tanggal' => $jadwalPerTanggal
            ]
        ], 200);
    }
}