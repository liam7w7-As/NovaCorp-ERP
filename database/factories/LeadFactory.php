<?php

namespace Database\Factories;

use App\Models\CanalWhatsapp;
use App\Models\EtapaCrm;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $telefono = '591'.fake()->unique()->numerify('7#######');

        return [
            'etapa_crm_id' => EtapaCrm::factory(),
            'canal_whatsapp_id' => CanalWhatsapp::factory(),
            'vendedor_id' => User::factory()->state(['rol' => 'vendedor', 'activo' => true]),
            'nombre' => fake()->name(),
            'telefono' => '+'.$telefono,
            'telefono_normalizado' => $telefono,
            'correo' => fake()->optional()->safeEmail(),
            'empresa' => fake()->optional()->company(),
            'ciudad' => fake()->city(),
            'origen' => 'manual',
            'tipo_consulta' => fake()->randomElement(['informacion', 'precio', 'otro']),
            'valor_estimado' => fake()->randomFloat(2, 0, 10000),
            'etapa_actualizada_at' => now(),
        ];
    }
}
