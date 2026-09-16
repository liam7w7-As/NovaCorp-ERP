<?php

namespace Database\Factories;

use App\Models\EtapaCrm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EtapaCrm>
 */
class EtapaCrmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->words(2, true);

        return [
            'nombre' => ucfirst($nombre),
            'slug' => str($nombre)->slug(),
            'orden' => fake()->unique()->numberBetween(10, 500),
            'color' => fake()->hexColor(),
            'tipo' => 'activa',
            'activa' => true,
        ];
    }
}
