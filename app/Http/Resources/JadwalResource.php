<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JadwalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'tanggal'     => $this->tanggal?->toDateString(),
            'jam_mulai'   => $this->jam_mulai,
            'jam_selesai' => $this->jam_selesai,
            'status'      => $this->status,
            // hanya muncul jika query memakai withExists('konsultasi as sudah_dipesan')
            'sudah_dipesan' => $this->when(
                array_key_exists('sudah_dipesan', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('sudah_dipesan')
            ),
        ];
    }
}
