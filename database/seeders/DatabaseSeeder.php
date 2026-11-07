<?php

namespace Database\Seeders;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Dados para demonstrar o sistema na entrevista, sem precisar cadastrar nada.
 *
 *  - Carla Mendes  (admin@viagens.test)   → administradora
 *  - Diego Ramos   (diretor@viagens.test) → administrador; aprova as viagens da Carla
 *  - Ana Souza     (ana@viagens.test)     → colaboradora
 *  - Bruno Lima    (bruno@viagens.test)   → colaborador
 *
 * Todos com a senha "senha123".
 *
 * Os colaboradores ganham pedidos em todos os status, incluindo pedidos
 * parados há dias (aparecem destacados como atrasados). A Carla também tem
 * uma viagem esperando, que só o Diego pode aprovar.
 *
 * Pode rodar mais de uma vez sem duplicar nada: se o usuário já existe, pula.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $carla = $this->criarUsuario('Carla Mendes', 'admin@viagens.test', administrador: true);
        $diego = $this->criarUsuario('Diego Ramos', 'diretor@viagens.test', administrador: true);

        if ($carla->wasRecentlyCreated) {
            // Viagem da própria administradora: ela não pode aprovar, o Diego pode.
            PedidoViagem::factory()->for($carla)->aguardandoHa(dias: 1)
                ->create(['destino' => 'Brasília, DF']);
        }

        $colaboradores = [
            ['Ana Souza', 'ana@viagens.test', 'Recife, PE'],
            ['Bruno Lima', 'bruno@viagens.test', 'Porto Alegre, RS'],
        ];

        foreach ($colaboradores as $i => [$nome, $email, $destinoAtrasado]) {
            $colaborador = $this->criarUsuario($nome, $email);

            if (! $colaborador->wasRecentlyCreated) {
                continue;
            }

            PedidoViagem::factory()->for($colaborador)->count(2)->create();
            PedidoViagem::factory()->for($colaborador)->aprovado(por: $carla)->create();
            PedidoViagem::factory()->for($colaborador)->cancelado(por: $diego)->create();

            // Parado há dias esperando decisão → destacado como atrasado.
            PedidoViagem::factory()->for($colaborador)->aguardandoHa(dias: 3 + $i * 2, horasExtras: 5)
                ->create(['destino' => $destinoAtrasado]);
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
