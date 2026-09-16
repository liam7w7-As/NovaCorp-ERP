<?php

namespace Database\Factories;

use App\Models\CanalWhatsapp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanalWhatsapp>
 */
class CanalWhatsappFactory extends Factory
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
            'nombre' => 'Línea '.fake()->city(),
            'ciudad' => fake()->city(),
            'telefono' => '+'.$telefono,
            'telefono_normalizado' => $telefono,
            'estado' => 'pendiente',
            'activo' => true,
        ];
    }
}
