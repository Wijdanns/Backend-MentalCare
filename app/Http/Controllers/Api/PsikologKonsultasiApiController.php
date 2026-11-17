<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPsikolog;
use App\Http\Controllers\Controller;
use App\Http\Resources\KonsultasiResource;
use Illuminate\Http\Request;

/** Daftar konsultasi masuk untuk psikolog (hanya yang sudah dibayar: Dikonfirmasi / Selesai). */
class PsikologKonsultasiApiController extends Controller
{
    use ResolvesPsikolog;

    public function index(Request $request)
    {
        $psikolog = $this->psikologSaya($request);

        $konsultasi = $psikolog->konsultasi()
            ->with(['user', 'jadwal'])
            ->whereIn('status_konsultasi', ['Dikonfirmasi', 'Selesai'])
            ->when(
                in_array($request->query('status'), ['Dikonfirmasi', 'Selesai'], true),
                fn ($q) => $q->where('status_konsultasi', $request->query('status'))
            )
            ->latest()
            ->paginate(15);

        $konsultasi->through(fn ($k) => (new KonsultasiResource($k))->resolve());

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar Konsultasi Masuk Berhasil Diambil',
            'data'    => $konsultasi,
        ], 200);
    }

    public function selesai(Request $request, $id)
    {
        $konsultasi = $this->psikologSaya($request)->konsultasi()
            ->with(['user', 'jadwal'])
            ->find($id);

        if (! $konsultasi) {
            return response()->json(['status' => 'error', 'message' => 'Konsultasi tidak ditemukan.'], 404);
        }

        if ($konsultasi->status_konsultasi !== 'Dikonfirmasi') {
            return response()->json([
                'status'  => 'error',
                'message' => "Hanya konsultasi berstatus Dikonfirmasi yang bisa diselesaikan (saat ini: {$konsultasi->status_konsultasi}).",
            ], 409);
        }

        $konsultasi->update(['status_konsultasi' => 'Selesai']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Sesi konsultasi ditandai selesai.',
            'data'    => new KonsultasiResource($konsultasi),
        ], 200);
    }
}
