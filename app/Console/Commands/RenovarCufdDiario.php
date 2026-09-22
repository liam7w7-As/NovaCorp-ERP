<?php

namespace App\Console\Commands;

use App\Models\PuntoVenta;
use App\Services\SiatService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('siat:renovar-cufd {--forzar : Renueva aunque el CUFD tenga más de una hora de vigencia}')]
#[Description('Renueva los CUFD ausentes, vencidos o próximos a vencer de los puntos de venta activos')]
class RenovarCufdDiario extends Command
{
    public function handle(SiatService $siat): int
    {
        $puntos = PuntoVenta::activos()->with('sucursal')->get();
        $ok = 0;
        $fallos = 0;
        $omitidos = 0;
        $umbralRenovacion = now()->addHour();

        foreach ($puntos as $pv) {
            if (empty($pv->cuis)) {
                $this->warn("POS {$pv->id} ({$pv->nombre}): sin CUIS, se omite.");

                continue;
            }

            if (! $this->option('forzar')
                && $pv->cufd
                && $pv->cufd_vigencia
                && $pv->cufd_vigencia->greaterThan($umbralRenovacion)) {
                $omitidos++;
                $this->line("POS {$pv->id} ({$pv->nombre}): vigente hasta {$pv->cufd_vigencia->format('d/m/Y H:i')}, se omite.");

                continue;
            }
            try {
                $siat->solicitarCufd(
                    (int) ($pv->sucursal->codigo ?? 0),
                    (int) $pv->codigo,
                    $pv->cuis,
                    $pv->id
                );
                $ok++;
                $this->info("POS {$pv->id} ({$pv->nombre}): CUFD renovado.");
            } catch (\Throwable $e) {
                $fallos++;
                Log::warning('RenovarCufdDiario falló', ['pos' => $pv->id, 'error' => $e->getMessage()]);
                $this->error("POS {$pv->id} ({$pv->nombre}): {$e->getMessage()}");
            }
        }

        $this->line("Listo: {$ok} renovado(s), {$omitidos} aún vigente(s), {$fallos} fallo(s).");

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
