<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PembayaranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'metode_pembayaran'  => $this->metode_pembayaran,
            'status_pembayaran'  => $this->status_pembayaran,
            'waktu_pembayaran'   => $this->waktu_pembayaran?->toIso8601String(),
            'sudah_unggah_bukti' => filled($this->bukti_pembayaran),
            // file bukti hanya bisa diambil pemiliknya lewat endpoint ber-token
            'bukti_url'          => $this->when(
                filled($this->bukti_pembayaran),
                fn () => url("/api/konsultasi/{$this->id_konsultasi}/bukti")
            ),
        ];
    }
}
