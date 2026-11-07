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

    public function aprovado(): static
    {
        return $this->state(['status' => StatusPedido::Aprovado]);
    }

    public function cancelado(): static
    {
        return $this->state(['status' => StatusPedido::Cancelado]);
    }
}
