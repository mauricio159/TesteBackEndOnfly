<?php

namespace Tests\Feature\PedidosViagem;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Requisito: "Listar todos os pedidos de viagem", com filtro por status,
 * por período (de criação ou de viagem) e por destino.
 */
class ListarPedidosViagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_colaborador_ve_somente_os_proprios_pedidos(): void
    {
        $ana = Usuario::factory()->create();
        PedidoViagem::factory()->for($ana)->count(2)->create();
        PedidoViagem::factory()->count(3)->create(); // pedidos de outras pessoas
        Sanctum::actingAs($ana);

        $this->getJson('/api/v1/pedidos-viagem')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_administrador_ve_os_pedidos_de_todos(): void
    {
        PedidoViagem::factory()->count(4)->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->getJson('/api/v1/pedidos-viagem')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_filtra_por_status(): void
    {
        $ana = Usuario::factory()->create();
        PedidoViagem::factory()->for($ana)->count(2)->create();
        $aprovado = PedidoViagem::factory()->for($ana)->aprovado()->create();
        PedidoViagem::factory()->for($ana)->cancelado()->create();
        Sanctum::actingAs($ana);

        $this->getJson('/api/v1/pedidos-viagem?status=aprovado')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $aprovado->id);
    }

    public function test_filtra_por_destino_com_busca_parcial_e_sem_diferenciar_maiusculas(): void
    {
        $ana = Usuario::factory()->create();
        $lisboa = PedidoViagem::factory()->for($ana)->create(['destino' => 'Lisboa, Portugal']);
        PedidoViagem::factory()->for($ana)->create(['destino' => 'Recife, PE']);
        Sanctum::actingAs($ana);

        $this->getJson('/api/v1/pedidos-viagem?destino=lisboa')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lisboa->id);
    }

    public function test_percentual_no_destino_e_tratado_como_texto_e_nao_como_curinga(): void
    {
        $ana = Usuario::factory()->create();
        PedidoViagem::factory()->for($ana)->create(['destino' => 'Recife, PE']);
        Sanctum::actingAs($ana);

        // %25 é o "%" codificado na URL. Se não fosse escapado, traria todos os pedidos.
        $this->getJson('/api/v1/pedidos-viagem?destino=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filtra_por_periodo_da_viagem(): void
    {
        $ana = Usuario::factory()->create();
        $dentro = PedidoViagem::factory()->for($ana)->create(['data_ida' => '2026-12-05', 'data_volta' => '2026-12-10']);
        PedidoViagem::factory()->for($ana)->create(['data_ida' => '2026-11-28', 'data_volta' => '2026-12-03']); // começa antes
        PedidoViagem::factory()->for($ana)->create(['data_ida' => '2026-12-28', 'data_volta' => '2027-01-03']); // termina depois
        Sanctum::actingAs($ana);

        $this->getJson('/api/v1/pedidos-viagem?viagem_de=2026-12-01&viagem_ate=2026-12-31')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentro->id);
    }

    public function test_filtra_por_periodo_de_criacao_do_pedido(): void
    {
        $ana = Usuario::factory()->create();
        PedidoViagem::factory()->for($ana)->create(['criado_em' => '2026-10-15 12:00:00']);
        $noPeriodo = PedidoViagem::factory()->for($ana)->create(['criado_em' => '2026-11-01 23:59:00']); // último minuto do dia
        PedidoViagem::factory()->for($ana)->create(['criado_em' => '2026-11-02 00:00:01']);
        Sanctum::actingAs($ana);

        $this->getJson('/api/v1/pedidos-viagem?criado_de=2026-10-20&criado_ate=2026-11-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $noPeriodo->id);
    }

    public function test_filtros_podem_ser_combinados(): void
    {
        $ana = Usuario::factory()->create();
        $esperado = PedidoViagem::factory()->for($ana)->aprovado()->create(['destino' => 'Salvador, BA']);
        PedidoViagem::factory()->for($ana)->create(['destino' => 'Salvador, BA']);
        PedidoViagem::factory()->for($ana)->aprovado()->create(['destino' => 'Curitiba, PR']);
        Sanctum::actingAs($ana);

        $this->getJson('/api/v1/pedidos-viagem?status=aprovado&destino=salvador')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $esperado->id);
    }

    public function test_resultado_e_paginado(): void
    {
        $ana = Usuario::factory()->create();
        PedidoViagem::factory()->for($ana)->count(5)->create();
        Sanctum::actingAs($ana);

        $this->getJson('/api/v1/pedidos-viagem?por_pagina=2&pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_filtros_invalidos_sao_recusados(): void
    {
        Sanctum::actingAs(Usuario::factory()->create());

        $this->getJson('/api/v1/pedidos-viagem?status=pendente&viagem_de=2026-12-10&viagem_ate=2026-12-01&por_pagina=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'viagem_ate', 'por_pagina']);
    }
}
