<?php

use App\Models\Konsultasi;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Batalkan otomatis konsultasi yang tidak dibayar dalam 1 jam
Schedule::call(fn () => Konsultasi::batalkanYangKedaluwarsa())->everyMinute();
