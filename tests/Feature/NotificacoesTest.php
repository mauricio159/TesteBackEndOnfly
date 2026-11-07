<?php

namespace Tests\Feature;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Teste de ponta a ponta da notificação, sem "fake": o administrador aprova,
 * a notificação passa pela fila (síncrona nos testes) e o colaborador a
 * encontra em GET /notificacoes.
 */
class NotificacoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_colaborador_encontra_o_aviso_de_aprovacao_nas_notificacoes(): void
    {
        $ana = Usuario::factory()->create(['nome' => 'Ana Souza']);
        $pedido = PedidoViagem::factory()->for($ana)->create(['destino' => 'Recife, PE']);

        Sanctum::actingAs(Usuario::factory()->administrador()->create(['nome' => 'Carla Mendes']));
        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => 'aprovado'])->assertOk();

        Sanctum::actingAs($ana);
        $this->getJson('/api/v1/notificacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.dados.tipo', 'status_alterado')
            ->assertJsonPath('data.0.dados.pedido_id', $pedido->id)
            ->assertJsonPath('data.0.dados.decidido_por', 'Carla Mendes')
            ->assertJsonPath('data.0.dados.mensagem', 'Seu pedido de viagem para Recife, PE foi aprovado por Carla Mendes.');
    }

    public function test_cada_um_ve_somente_as_proprias_notificacoes(): void
    {
        $pedido = PedidoViagem::factory()->create();

        Sanctum::actingAs(Usuario::factory()->administrador()->create());
        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => 'cancelado'])->assertOk();

        Sanctum::actingAs(Usuario::factory()->create());
        $this->getJson('/api/v1/notificacoes')->assertOk()->assertJsonCount(0, 'data');
    }
}
