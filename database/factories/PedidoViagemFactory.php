<?php

namespace Database\Factories;

use App\Enums\StatusPedido;
use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Gera pedidos de viagem com cara de verdade: destinos brasileiros, ida
 * entre uma semana e três meses à frente e volta de 1 a 14 dias depois.
 *
 * @extends Factory<PedidoViagem>
 */
class PedidoViagemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ida = Carbon::instance(fake()->dateTimeBetween('+1 week', '+3 months'))->startOfDay();

        return [
            'usuario_id' => Usuario::factory(),
            // Mesmo comportamento da API: o nome do solicitante é o do dono do pedido.
            'nome_solicitante' => fn (array $atributos) => Usuario::find($atributos['usuario_id'])?->nome ?? fake()->name(),
            'destino' => fake()->randomElement([
                'São Paulo, SP', 'Rio de Janeiro, RJ', 'Belo Horizonte, MG', 'Curitiba, PR',
                'Porto Alegre, RS', 'Recife, PE', 'Salvador, BA', 'Florianópolis, SC',
                'Brasília, DF', 'Fortaleza, CE', 'Manaus, AM', 'Lisboa, Portugal',
            ]),
            'data_ida' => $ida->toDateString(),
            'data_volta' => $ida->copy()->addDays(fake()->numberBetween(1, 14))->toDateString(),
            'status' => StatusPedido::Solicitado,
        ];
    }

    /**
     * Pedido já aprovado, com o registro de quem aprovou e quando (como na vida real).
     * Sem informar o administrador, a factory cria um.
     */
    public function aprovado(?Usuario $por = null): static
    {
        return $this->decidido(StatusPedido::Aprovado, $por);
    }

    public function cancelado(?Usuario $por = null): static
    {
        return $this->decidido(StatusPedido::Cancelado, $por);
    }

    /**
     * Pedido feito há alguns dias e ainda sem decisão (vira "atrasado" passado o limite).
     */
    public function aguardandoHa(int $dias, int $horasExtras = 0): static
    {
        $criadoEm = now()->subDays($dias)->subHours($horasExtras);

        return $this->state(['criado_em' => $criadoEm, 'atualizado_em' => $criadoEm]);
    }

    private function decidido(StatusPedido $status, ?Usuario $por): static
    {
        return $this->state([
            'status' => $status,
            'decidido_por_id' => $por?->id ?? Usuario::factory()->administrador(),
            'decidido_em' => now(),
        ]);
    }
}
