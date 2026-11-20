<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'));

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
