<?php

namespace Tests\Feature\PedidosViagem;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pedido parado há tempo demais esperando decisão: a API informa há quanto
 * tempo ele aguarda e marca "atrasado" para o front destacar.
 * O limite padrão é 48 horas (config/pedidos.php).
 */
class PedidosAtrasadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-07 12:00:00');
        config(['pedidos.alerta_apos_horas' => 48]);
    }

    public function test_pedido_solicitado_ha_mais_de_48h_vem_marcado_como_atrasado(): void
    {
        $pedido = PedidoViagem::factory()->aguardandoHa(dias: 3, horasExtras: 4)->create();
        Sanctum::actingAs($pedido->usuario);

        $this->getJson("/api/v1/pedidos-viagem/{$pedido->id}")
            ->assertOk()
            ->assertJsonPath('data.atrasado', true)
            ->assertJsonPath('data.tempo_aguardando', 'há 3 dias e 4 horas');
    }

    public function test_pedido_recente_mostra_o_tempo_mas_nao_esta_atrasado(): void
    {
        $pedido = PedidoViagem::factory()->aguardandoHa(dias: 1)->create();
        Sanctum::actingAs($pedido->usuario);

        $this->getJson("/api/v1/pedidos-viagem/{$pedido->id}")
            ->assertJsonPath('data.atrasado', false)
            ->assertJsonPath('data.tempo_aguardando', 'há 1 dia');
    }

    public function test_pedido_ja_decidido_nunca_fica_atrasado(): void
    {
        // Antigo, mas já aprovado: não há mais nada esperando.
        $pedido = PedidoViagem::factory()->aguardandoHa(dias: 10)->aprovado()->create();
        Sanctum::actingAs($pedido->usuario);

        $this->getJson("/api/v1/pedidos-viagem/{$pedido->id}")
            ->assertJsonPath('data.atrasado', false)
            ->assertJsonPath('data.tempo_aguardando', null);
    }

    public function test_o_limite_vem_da_configuracao(): void
    {
        config(['pedidos.alerta_apos_horas' => 12]);
        $pedido = PedidoViagem::factory()->aguardandoHa(dias: 0, horasExtras: 13)->create();
        Sanctum::actingAs($pedido->usuario);

        $this->getJson("/api/v1/pedidos-viagem/{$pedido->id}")->assertJsonPath('data.atrasado', true);
    }

    public function test_filtra_somente_os_atrasados(): void
    {
        $atrasado = PedidoViagem::factory()->aguardandoHa(dias: 5)->create();
        PedidoViagem::factory()->aguardandoHa(dias: 1)->create();
        PedidoViagem::factory()->aguardandoHa(dias: 9)->cancelado()->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->getJson('/api/v1/pedidos-viagem?atrasados=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $atrasado->id);
    }
}
