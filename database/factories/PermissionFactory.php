<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->randomElement(['employees', 'attendance', 'payroll']);
        $action = fake()->randomElement(['view', 'manage']);

        return [
            'name' => ucfirst($action).' '.$group,
            'slug' => $group.'.'.$action,
            'group' => $group,
        ];
    }
}
