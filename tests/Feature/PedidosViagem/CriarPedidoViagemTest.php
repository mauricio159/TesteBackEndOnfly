<?php

namespace Tests\Feature\PedidosViagem;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Requisito: "Criar um pedido de viagem" com ID, nome do solicitante,
 * destino, data de ida, data de volta e status.
 */
class CriarPedidoViagemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // "Congela" o relógio para a regra "a ida não pode ser no passado"
        // dar sempre o mesmo resultado, não importa o dia em que o teste rodar.
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
    }

    public function test_colaborador_cria_um_pedido_de_viagem(): void
    {
        $ana = Usuario::factory()->create(['nome' => 'Ana Souza']);
        Sanctum::actingAs($ana);

        $resposta = $this->postJson('/api/v1/pedidos-viagem', [
            'destino' => 'Lisboa, Portugal',
            'data_ida' => '2026-12-10',
            'data_volta' => '2026-12-15',
        ]);

        $resposta->assertCreated()
            ->assertJsonPath('data.solicitante.id', $ana->id)
            ->assertJsonPath('data.solicitante.nome', 'Ana Souza')
            ->assertJsonPath('data.destino', 'Lisboa, Portugal')
            ->assertJsonPath('data.data_ida', '2026-12-10')
            ->assertJsonPath('data.data_volta', '2026-12-15')
            ->assertJsonPath('data.status', 'solicitado')
            ->assertJsonPath('data.status_rotulo', 'Solicitado');

        $this->assertTrue(str($resposta->json('data.id'))->isUuid());

        $this->assertDatabaseHas('pedidos_viagem', [
            'id' => $resposta->json('data.id'),
            'usuario_id' => $ana->id,
            'nome_solicitante' => 'Ana Souza',
            'status' => 'solicitado',
        ]);
    }

    public function test_nao_da_para_forjar_solicitante_nem_status(): void
    {
        // Mesmo mandando outro nome, outro dono e status "aprovado",
        // o pedido nasce do usuário logado e como "solicitado".
        $ana = Usuario::factory()->create(['nome' => 'Ana Souza']);
        Sanctum::actingAs($ana);

        $this->postJson('/api/v1/pedidos-viagem', [
            'destino' => 'Recife, PE',
            'data_ida' => '2026-12-10',
            'data_volta' => '2026-12-12',
            'nome_solicitante' => 'Outra Pessoa',
            'status' => 'aprovado',
            'usuario_id' => 999,
        ])->assertCreated()
            ->assertJsonPath('data.solicitante.nome', 'Ana Souza')
            ->assertJsonPath('data.solicitante.id', $ana->id)
            ->assertJsonPath('data.status', 'solicitado');
    }

    public function test_campos_obrigatorios(): void
    {
        Sanctum::actingAs(Usuario::factory()->create());

        $this->postJson('/api/v1/pedidos-viagem', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['destino', 'data_ida', 'data_volta'])
            ->assertJsonPath('errors.data_ida.0', 'O campo data de ida é obrigatório.');
    }

    public function test_data_de_ida_nao_pode_estar_no_passado(): void
    {
        Sanctum::actingAs(Usuario::factory()->create());

        $this->postJson('/api/v1/pedidos-viagem', [
            'destino' => 'Recife, PE',
            'data_ida' => '2026-11-05',
            'data_volta' => '2026-11-10',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.data_ida.0', 'A data de ida não pode estar no passado.');
    }

    public function test_volta_nao_pode_ser_antes_da_ida(): void
    {
        Sanctum::actingAs(Usuario::factory()->create());

        $this->postJson('/api/v1/pedidos-viagem', [
            'destino' => 'Recife, PE',
            'data_ida' => '2026-12-10',
            'data_volta' => '2026-12-09',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.data_volta.0', 'A data de volta deve ser igual ou posterior à data de ida.');
    }

    public function test_bate_e_volta_no_mesmo_dia_e_permitido(): void
    {
        Sanctum::actingAs(Usuario::factory()->create());

        $this->postJson('/api/v1/pedidos-viagem', [
            'destino' => 'Curitiba, PR',
            'data_ida' => '2026-11-06',
            'data_volta' => '2026-11-06',
        ])->assertCreated();
    }

    public function test_datas_devem_vir_no_formato_iso(): void
    {
        // 10/12/2026 é ambíguo (10 de dezembro ou 12 de outubro?), por isso a API exige AAAA-MM-DD.
        Sanctum::actingAs(Usuario::factory()->create());

        $this->postJson('/api/v1/pedidos-viagem', [
            'destino' => 'Recife, PE',
            'data_ida' => '10/12/2026',
            'data_volta' => '15/12/2026',
        ])->assertUnprocessable()->assertJsonValidationErrors(['data_ida', 'data_volta']);
    }

    public function test_visitante_sem_login_nao_cria_pedido(): void
    {
        $this->postJson('/api/v1/pedidos-viagem', [
            'destino' => 'Recife, PE',
            'data_ida' => '2026-12-10',
            'data_volta' => '2026-12-12',
        ])->assertUnauthorized();
    }
}
