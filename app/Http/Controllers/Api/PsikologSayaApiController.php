<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPsikolog;
use App\Http\Controllers\Controller;
use App\Http\Resources\PsikologResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PsikologSayaApiController extends Controller
{
    use ResolvesPsikolog;

    public function profil(Request $request)
    {
        return $this->respons($this->psikologSaya($request), 'Profil Psikolog Berhasil Diambil');
    }

    /**
     * Ubah nama, spesialis, deskripsi, foto, dan (opsional) password.
     * Biaya & email sengaja tidak bisa diubah di sini (dikelola admin).
     * Nama otomatis tersinkron ke tabel users lewat Psikolog::booted().
     */
    public function update(Request $request)
    {
        $psikolog = $this->psikologSaya($request);

        $data = $request->validate([
            'nama'               => ['required', 'string', 'max:255'],
            'spesialis'          => ['required', 'string', 'max:255'],
            'deskripsi_psikolog' => ['nullable', 'string'],
            'foto'               => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'password'           => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $update = collect($data)->only(['nama', 'spesialis', 'deskripsi_psikolog'])->all();

        if ($request->hasFile('foto')) {
            if ($psikolog->foto_profil) {
                Storage::disk('public')->delete($psikolog->foto_profil);
            }
            $update['foto_profil'] = $request->file('foto')->store('psikolog', 'public');
        }

        $psikolog->update($update);

        if (! empty($data['password'])) {
            $request->user()->update(['password' => $data['password']]);
        }

        return $this->respons($psikolog->fresh(), 'Profil berhasil diperbarui!');
    }

    private function respons($psikolog, string $pesan)
    {
        return response()->json([
            'status'  => 'success',
            'message' => $pesan,
            'data'    => array_merge(
                (new PsikologResource($psikolog))->resolve(),
                ['email' => $psikolog->email] // email milik sendiri, aman ditampilkan
            ),
        ], 200);
    }
}
