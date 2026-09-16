<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:database')->dailyAt('02:00');

// El CUFD vence cada 24h: renovarlo de madrugada evita cortes al facturar.
Schedule::command('siat:renovar-cufd')->dailyAt('00:05');
