<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtikelResource extends JsonResource
{
    protected bool $sertakanIsi = false;

    /** Dipakai di halaman detail saja; daftar artikel tidak perlu isi penuh. */
    public function sertakanIsi(): static
    {
        $this->sertakanIsi = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'judul'             => $this->judul,
            'ringkasan'         => $this->ringkasan,
            'isi'               => $this->when($this->sertakanIsi, fn () => $this->isi),
            'gambar_url'        => $this->thumbnail
                ? route('foto-artikel', basename($this->thumbnail))
                : null,
            'tanggal_publikasi' => $this->tanggal_publikasi?->toDateString(),
            'waktu_baca'        => $this->waktu_baca,
            'kategori'          => $this->whenLoaded('kategori', fn () => $this->kategori
                ? ['id' => $this->kategori->getKey(), 'nama_kategori' => $this->kategori->nama_kategori]
                : null),
            // hanya nama penulis, bukan email/role
            'penulis'           => $this->whenLoaded('user', fn () => $this->user?->nama),
        ];
    }
}
