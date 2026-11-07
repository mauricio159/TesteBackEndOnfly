<?php

namespace Tests\Feature\PedidosViagem;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Requisito: "Consultar um pedido de viagem" pelo ID.
 */
class ConsultarPedidoViagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_dono_consulta_o_proprio_pedido(): void
    {
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs($pedido->usuario);

        $this->getJson("/api/v1/pedidos-viagem/{$pedido->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $pedido->id)
            ->assertJsonPath('data.destino', $pedido->destino)
            ->assertJsonPath('data.status', 'solicitado')
            ->assertJsonStructure(['data' => [
                'id', 'solicitante' => ['id', 'nome'], 'destino', 'data_ida',
                'data_volta', 'status', 'status_rotulo', 'criado_em', 'atualizado_em',
            ]]);
    }

    public function test_administrador_consulta_qualquer_pedido(): void
    {
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->getJson("/api/v1/pedidos-viagem/{$pedido->id}")->assertOk();
    }

    public function test_colaborador_nao_ve_pedido_de_outro_colaborador(): void
    {
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs(Usuario::factory()->create());

        $this->getJson("/api/v1/pedidos-viagem/{$pedido->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Você só pode consultar os seus próprios pedidos de viagem.');
    }

    public function test_pedido_inexistente_devolve_404(): void
    {
        Sanctum::actingAs(Usuario::factory()->create());

        $this->getJson('/api/v1/pedidos-viagem/'.Str::uuid7())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Pedido de viagem não encontrado.']);
    }
}
