<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            // Padrão deliberadamente restritivo: um teste que precise
            // de poder de escrita declara `->admin()`, deixando a
            // intenção visível no próprio teste. O inverso — admin por
            // padrão — faria um teste de autorização passar por
            // acidente.
            'role' => UserRole::Viewer,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Perfil administrador: pode alterar o cadastro de clientes. */
    public function admin(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Admin]);
    }

    /** Perfil leitura: pode apenas consultar e filtrar. */
    public function viewer(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Viewer]);
    }
}
