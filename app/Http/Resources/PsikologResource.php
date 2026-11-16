<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data psikolog untuk publik. Sengaja TIDAK memuat email / id_user
 * (email psikolog = kredensial login).
 */
class PsikologResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'nama'               => $this->nama,
            'spesialis'          => $this->spesialis,
            'deskripsi_psikolog' => $this->deskripsi_psikolog,
            'biaya'              => (int) $this->biaya,
            'foto_url'           => $this->foto_profil
                ? route('foto-psikolog', basename($this->foto_profil))
                : null,
            // muncul jika query memakai withExists('jadwalTersedia as tersedia') / diset manual
            'tersedia' => $this->when(
                array_key_exists('tersedia', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('tersedia')
            ),
            'jadwal_tersedia' => JadwalResource::collection($this->whenLoaded('jadwalTersedia')),
        ];
    }
}
