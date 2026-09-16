<?php

namespace Database\Seeders;

use App\Models\EtapaCrm;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class EtapasCrmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $etapas = [
            ['nombre' => 'Menú', 'slug' => 'menu', 'orden' => 1, 'color' => '#64748B', 'tipo' => 'activa'],
            ['nombre' => 'Información', 'slug' => 'informacion', 'orden' => 2, 'color' => '#0891B2', 'tipo' => 'activa'],
            ['nombre' => 'Precio', 'slug' => 'precio', 'orden' => 3, 'color' => '#D97706', 'tipo' => 'activa'],
            ['nombre' => 'Por atender', 'slug' => 'por-atender', 'orden' => 4, 'color' => '#7C3AED', 'tipo' => 'activa'],
            ['nombre' => 'Atendido', 'slug' => 'atendido', 'orden' => 5, 'color' => '#0F766E', 'tipo' => 'activa'],
            ['nombre' => 'Interesado', 'slug' => 'interesado', 'orden' => 6, 'color' => '#2563EB', 'tipo' => 'activa'],
            ['nombre' => 'Por pagar', 'slug' => 'por-pagar', 'orden' => 7, 'color' => '#EA580C', 'tipo' => 'activa'],
            ['nombre' => 'Compró', 'slug' => 'compro', 'orden' => 8, 'color' => '#15803D', 'tipo' => EtapaCrm::GANADA],
            ['nombre' => 'Perdido', 'slug' => 'perdido', 'orden' => 9, 'color' => '#475569', 'tipo' => EtapaCrm::PERDIDA],
        ];

        foreach ($etapas as $etapa) {
            EtapaCrm::updateOrCreate(
                ['slug' => $etapa['slug']],
                [...$etapa, 'activa' => true]
            );
        }

        $menu = EtapaCrm::where('slug', EtapaCrm::ENTRADA)->firstOrFail();
        $etapaAnterior = EtapaCrm::where('slug', 'nuevo')->first();

        if ($etapaAnterior) {
            Lead::where('etapa_crm_id', $etapaAnterior->id)->update(['etapa_crm_id' => $menu->id]);
        }

        EtapaCrm::whereNotIn('slug', array_column($etapas, 'slug'))->update(['activa' => false]);
    }
}
