<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => fake()->unique()->bothify('EMP-####'),
            'first_name' => fake('es_MX')->firstName(),
            'last_name' => fake('es_MX')->lastName(),
            'second_last_name' => fake('es_MX')->lastName(),
            'branch_id' => Branch::factory(),
            'position' => fake('es_MX')->jobTitle(),
            'department' => fake('es_MX')->randomElement(['Operaciones', 'Administración', 'Ventas']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake('es_MX')->numerify('55########'),
            'hire_date' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'termination_date' => null,
            'status' => EmployeeStatus::Active,
            'pin_hash' => bcrypt(Str::random(16)),
            'pin_updated_at' => now(),
            'user_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmployeeStatus::Inactive,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmployeeStatus::Terminated,
            'termination_date' => now()->subMonth()->format('Y-m-d'),
        ]);
    }
}
