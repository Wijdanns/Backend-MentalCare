<?php

namespace Database\Seeders;

use App\Models\JadwalPsikolog;
use Illuminate\Database\Seeder;

class JadwalPsikologSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'id_psikolog' => 1, // Dr. Ani Wijaya
                'tanggal' => now()->addDays(2)->format('Y-m-d'),
                'jam_mulai' => '10:00:00',
                'jam_selesai' => '11:00:00',
                'status' => 'Tersedia',
            ],
            [
                'id_psikolog' => 2, // Budi Santoso
                'tanggal' => now()->addDays(2)->format('Y-m-d'),
                'jam_mulai' => '13:00:00',
                'jam_selesai' => '14:00:00',
                'status' => 'Tersedia',
            ],
            [
                'id_psikolog' => 3, // Citra Lestari
                'tanggal' => now()->addDays(3)->format('Y-m-d'),
                'jam_mulai' => '15:00:00',
                'jam_selesai' => '16:00:00',
                'status' => 'Tersedia',
            ],
        ];

        foreach ($data as $item) {
            JadwalPsikolog::create($item);
        }
    }
}
