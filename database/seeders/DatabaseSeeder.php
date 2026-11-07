<?php

namespace Database\Seeders;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Dados para demonstrar o sistema na entrevista, sem precisar cadastrar nada.
 *
 *  - Carla Mendes  (admin@viagens.test)  → administradora, aprova/cancela pedidos
 *  - Ana Souza     (ana@viagens.test)    → colaboradora
 *  - Bruno Lima    (bruno@viagens.test)  → colaborador
 *
 * Todos com a senha "senha123". Cada colaborador ganha 3 pedidos solicitados,
 * 1 aprovado e 1 cancelado, para a listagem e os filtros já terem o que mostrar.
 *
 * Pode rodar mais de uma vez sem duplicar nada: se o usuário já existe, pula.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->criarUsuario('Carla Mendes', 'admin@viagens.test', administrador: true);

        $colaboradores = [
            ['Ana Souza', 'ana@viagens.test'],
            ['Bruno Lima', 'bruno@viagens.test'],
        ];

        foreach ($colaboradores as [$nome, $email]) {
            $colaborador = $this->criarUsuario($nome, $email);

            if ($colaborador->wasRecentlyCreated) {
                PedidoViagem::factory()->for($colaborador)->count(3)->create();
                PedidoViagem::factory()->for($colaborador)->aprovado()->create();
                PedidoViagem::factory()->for($colaborador)->cancelado()->create();
            }
        }
    }

    private function criarUsuario(string $nome, string $email, bool $administrador = false): Usuario
    {
        return Usuario::where('email', $email)->first()
            ?? Usuario::factory()
                ->state(['administrador' => $administrador])
                ->create(['nome' => $nome, 'email' => $email]);
    }
}
