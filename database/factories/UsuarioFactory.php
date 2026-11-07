<?php

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Gera usuários falsos para testes e para o seeder.
 * Como APP_FAKER_LOCALE=pt_BR, os nomes saem brasileiros ("Maria Oliveira" etc.).
 *
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    /** Guardamos o hash para não recalcular o bcrypt a cada usuário (testes mais rápidos). */
    protected static ?string $senha;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'senha' => static::$senha ??= Hash::make('senha123'),
            'administrador' => false,
        ];
    }

    /** Usuário com permissão de aprovar e cancelar pedidos. */
    public function administrador(): static
    {
        return $this->state(['administrador' => true]);
    }
}
