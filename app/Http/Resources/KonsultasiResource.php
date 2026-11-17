<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KonsultasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'status_konsultasi' => $this->status_konsultasi,
            'total_biaya'       => (int) $this->total_biaya,
            'batas_pembayaran'  => $this->when(
                $this->status_konsultasi === 'Menunggu Pembayaran',
                fn () => $this->batasPembayaran()->toIso8601String()
            ),
            'dibuat_pada'       => $this->created_at?->toIso8601String(),
            'psikolog'          => new PsikologResource($this->whenLoaded('psikolog')),
            'jadwal'            => new JadwalResource($this->whenLoaded('jadwal')),
            'pembayaran'        => new PembayaranResource($this->whenLoaded('pembayaran')),
            // terisi hanya di endpoint sisi psikolog (relasi user di-load)
            'pasien'            => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'nama'  => $this->user->nama,
                'email' => $this->user->email,
            ]),
        ];
    }
}
