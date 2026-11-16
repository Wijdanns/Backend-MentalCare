<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use Illuminate\Http\Request;

class ArtikelApiController extends Controller
{
    /**
     * Menampilkan daftar artikel (mendukung pencarian & filter kategori) dalam format JSON.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $kategoriId = $request->integer('kategori') ?: null;

        $artikels = Artikel::query()
            ->with(['kategori', 'user'])
            ->terbit()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('judul', 'like', "%{$q}%")
                  ->orWhere('isi', 'like', "%{$q}%");
            }))
            ->when($kategoriId, fn ($query) => $query->where('id_kategori', $kategoriId))
            ->orderByDesc('tanggal_publikasi')
            ->orderByDesc('id')
            ->paginate(9);

        return response()->json([
            'status' => 'success',
            'message' => 'List Data Artikel Berhasil Diambil',
            'data' => $artikels
        ], 200);
    }

    /**
     * Menampilkan detail satu artikel berdasarkan ID dalam format JSON.
     */
    public function show($id)
    {
        $artikel = Artikel::with(['kategori', 'user'])->terbit()->find($id);

        if (!$artikel) {
            return response()->json([
                'status' => 'error',
                'message' => 'Artikel tidak ditemukan atau belum terbit'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Artikel Berhasil Diambil',
            'data' => $artikel
        ], 200);
    }
}