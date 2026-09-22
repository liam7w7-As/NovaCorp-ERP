<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:database')->dailyAt('02:00');

// Revisa con frecuencia y renueva solo los CUFD que vencen en una hora o menos.
Schedule::command('siat:renovar-cufd')->everyTenMinutes()->withoutOverlapping(15);
