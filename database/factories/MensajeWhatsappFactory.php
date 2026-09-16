<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\MensajeWhatsapp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MensajeWhatsapp>
 */
class MensajeWhatsappFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'direccion' => 'entrante',
            'tipo' => 'texto',
            'contenido' => fake()->sentence(),
            'estado' => 'recibido',
            'ocurrio_at' => now(),
        ];
    }
}
