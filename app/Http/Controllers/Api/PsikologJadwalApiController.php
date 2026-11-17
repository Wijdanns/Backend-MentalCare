<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPsikolog;
use App\Http\Controllers\Controller;
use App\Http\Resources\JadwalResource;
use App\Models\JadwalPsikolog;
use App\Models\Psikolog;
use Illuminate\Http\Request;

/** CRUD jadwal milik psikolog yang sedang login (padanan panel Filament). */
class PsikologJadwalApiController extends Controller
{
    use ResolvesPsikolog;

    public function index(Request $request)
    {
        $jadwal = $this->psikologSaya($request)->jadwal()
            ->withExists('konsultasi as sudah_dipesan')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->paginate(15);

        $jadwal->through(fn ($j) => (new JadwalResource($j))->resolve());

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal Berhasil Diambil',
            'data'    => $jadwal,
        ], 200);
    }

    public function store(Request $request)
    {
        $psikolog = $this->psikologSaya($request);

        $data = $request->validate([
            'tanggal'     => ['required', 'date', 'after_or_equal:today'],
            'jam_mulai'   => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
        ]);

        if ($bentrok = $this->bentrok($psikolog, $data)) {
            return $this->responsBentrok($bentrok);
        }

        $jadwal = $psikolog->jadwal()->create($data + ['status' => 'Tersedia']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal berhasil ditambahkan.',
            'data'    => new JadwalResource($jadwal),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $psikolog = $this->psikologSaya($request);
        $jadwal = $psikolog->jadwal()->find($id);

        if (! $jadwal) {
            return $this->tidakDitemukan();
        }

        if ($jadwal->konsultasi()->exists()) {
            return $this->sudahDipesan();
        }

        $data = $request->validate([
            'tanggal'     => ['required', 'date', 'after_or_equal:today'],
            'jam_mulai'   => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'status'      => ['sometimes', 'in:Tersedia,Tidak Tersedia'],
        ]);

        if ($bentrok = $this->bentrok($psikolog, $data, $jadwal->id)) {
            return $this->responsBentrok($bentrok);
        }

        $jadwal->update($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal berhasil diperbarui.',
            'data'    => new JadwalResource($jadwal->fresh()),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $jadwal = $this->psikologSaya($request)->jadwal()->find($id);

        if (! $jadwal) {
            return $this->tidakDitemukan();
        }

        // konsultasi.id_jadwal memakai cascade delete → jangan hapus slot yang pernah dipesan
        if ($jadwal->konsultasi()->exists()) {
            return $this->sudahDipesan();
        }

        $jadwal->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal berhasil dihapus.',
        ], 200);
    }

    private function bentrok(Psikolog $psikolog, array $d, ?int $abaikanId = null): ?JadwalPsikolog
    {
        return $psikolog->jadwal()
            ->whereDate('tanggal', $d['tanggal'])
            ->when($abaikanId, fn ($q) => $q->where('id', '!=', $abaikanId))
            ->where('jam_mulai', '<', $d['jam_selesai'])
            ->where('jam_selesai', '>', $d['jam_mulai'])
            ->first();
    }

    private function responsBentrok(JadwalPsikolog $bentrok)
    {
        $mulai = substr($bentrok->jam_mulai, 0, 5);
        $selesai = substr($bentrok->jam_selesai, 0, 5);

        return response()->json([
            'status'  => 'error',
            'message' => "Jadwal bentrok dengan jadwal lain pukul {$mulai} - {$selesai} di tanggal yang sama.",
        ], 422);
    }

    private function sudahDipesan()
    {
        return response()->json([
            'status'  => 'error',
            'message' => 'Jadwal ini sudah pernah dipesan pasien sehingga tidak dapat diubah atau dihapus.',
        ], 409);
    }

    private function tidakDitemukan()
    {
        return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
    }
}
