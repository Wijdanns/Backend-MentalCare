<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Psikolog;
use Illuminate\Http\Request;

trait ResolvesPsikolog
{
    /** Profil psikolog milik akun yang sedang login (403 jika akun belum terhubung). */
    protected function psikologSaya(Request $request): Psikolog
    {
        return $request->user()->psikolog
            ?? abort(403, 'Akun ini belum terhubung ke profil psikolog.');
    }
}
