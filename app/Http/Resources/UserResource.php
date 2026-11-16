<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'nama'       => $this->nama,
            'email'      => $this->email,
            'role'       => $this->role,
            'profil_url' => $this->profil ? asset('storage/' . $this->profil) : null,
        ];
    }
}
