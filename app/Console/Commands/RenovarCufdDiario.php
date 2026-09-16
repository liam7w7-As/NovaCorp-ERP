<?php

namespace App\Console\Commands;

use App\Models\PuntoVenta;
use App\Services\SiatService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('siat:renovar-cufd')]
#[Description('Renueva el CUFD de los puntos de venta activos (vence cada 24h)')]
class RenovarCufdDiario extends Command
{
    public function handle(SiatService $siat): int
    {
        $puntos = PuntoVenta::activos()->with('sucursal')->get();
        $ok = 0;
        $fallos = 0;

        foreach ($puntos as $pv) {
            if (empty($pv->cuis)) {
                $this->warn("POS {$pv->id} ({$pv->nombre}): sin CUIS, se omite.");

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

        $this->line("Listo: {$ok} renovado(s), {$fallos} fallo(s).");

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
