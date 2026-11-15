<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfilApiController extends Controller
{
    /**
     * Menampilkan data profil user beserta riwayat konsultasi dan tes (format JSON).
     */
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $riwayatKonsultasi = $user->konsultasi()
            ->with(['psikolog', 'jadwal', 'pembayaran'])
            ->latest()
            ->get();

        $riwayatTes = $user->hasilTes()
            ->with('tesPsikologis')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data Profil Berhasil Diambil',
            'data' => [
                'user' => $user,
                'riwayat_konsultasi' => $riwayatKonsultasi,
                'riwayat_tes' => $riwayatTes,
            ]
        ], 200);
    }

    /**
     * Memperbarui informasi nama dan foto profil user (format JSON).
     */
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'nama'   => 'required|string|max:255',
            'profil' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $data = ['nama' => $request->nama];

        if ($request->hasFile('profil')) {
            if ($user->profil) {
                Storage::disk('public')->delete($user->profil);
            }
            $data['profil'] = $request->file('profil')->store('profil', 'public');
        }

        $user->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diperbarui!',
            'data' => $user
        ], 200);
    }
}