<?php

namespace App\Http\Controllers;

use App\Models\Psikolog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PsikologController extends Controller
{
    /**
     * Daftar psikolog untuk web, dengan pencarian dan filter.
     */
    public function index(Request $request): View
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
            ->paginate(9)
            ->withQueryString();

        $daftarSpesialis = Psikolog::query()
            ->select('spesialis')
            ->distinct()
            ->orderBy('spesialis')
            ->pluck('spesialis');

        return view('psikolog.index', compact('psikologs', 'daftarSpesialis', 'q', 'spesialis'));
    }

    /**
     * Detail satu psikolog beserta jadwal yang masih tersedia untuk web.
     */
    public function show(Psikolog $psikolog): View
    {
        $psikolog->loadExists('jadwalTersedia as tersedia');

        $jadwalPerTanggal = $psikolog->jadwalTersedia()
            ->get()
            ->groupBy(fn ($jadwal) => $jadwal->tanggal->toDateString());

        return view('psikolog.show', compact('psikolog', 'jadwalPerTanggal'));
    }
}