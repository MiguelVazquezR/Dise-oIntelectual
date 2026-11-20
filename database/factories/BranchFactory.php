<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Sucursal '.fake('es_MX')->city(),
            'code' => fake()->unique()->bothify('SUC-###'),
            'address' => fake('es_MX')->streetAddress(),
            'latitude' => '19.4326000',
            'longitude' => '-99.1332000',
            'geofence_radius_m' => 150,
            'timezone' => 'America/Mexico_City',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
