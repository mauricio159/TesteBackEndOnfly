<?php

namespace Tests\Feature\PedidosViagem;

use App\Enums\StatusPedido;
use App\Models\PedidoViagem;
use App\Models\Usuario;
use App\Notifications\StatusDoPedidoAlterado;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Requisitos cobertos aqui:
 *  - "Atualizar o status de um pedido de viagem" (aprovado ou cancelado);
 *  - só administrador pode alterar, e o solicitante não pode alterar o próprio;
 *  - só dá para cancelar se o pedido ainda não foi aprovado;
 *  - notificar o solicitante quando o pedido for aprovado ou cancelado.
 */
class AlterarStatusPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Intercepta as notificações: nada é enviado de verdade, mas
        // conseguimos verificar para quem iria e com qual conteúdo.
        Notification::fake();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function decisoesDoAdministrador(): array
    {
        return [
            'aprovar' => ['aprovado'],
            'cancelar' => ['cancelado'],
        ];
    }

    #[DataProvider('decisoesDoAdministrador')]
    public function test_administrador_aprova_ou_cancela_pedido_solicitado(string $status): void
    {
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => $status])
            ->assertOk()
            ->assertJsonPath('data.status', $status);

        $this->assertSame($status, $pedido->fresh()->status->value);
    }

    #[DataProvider('decisoesDoAdministrador')]
    public function test_solicitante_e_notificado_ao_aprovar_ou_cancelar(string $status): void
    {
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => $status])->assertOk();

        Notification::assertSentTo(
            $pedido->usuario,
            StatusDoPedidoAlterado::class,
            fn (StatusDoPedidoAlterado $n, array $canais) => $n->pedido->is($pedido)
                && $n->status->value === $status
                && $canais === ['mail', 'database'],
        );
        Notification::assertCount(1);
    }

    public function test_notificacao_vai_para_a_fila(): void
    {
        // Garantia de que o envio do e-mail não segura a resposta da API.
        $this->assertContains(ShouldQueue::class, class_implements(StatusDoPedidoAlterado::class));
    }

    public function test_conteudo_da_notificacao(): void
    {
        $pedido = PedidoViagem::factory()->create([
            'nome_solicitante' => 'Ana Souza',
            'destino' => 'Lisboa, Portugal',
            'data_ida' => '2026-12-10',
            'data_volta' => '2026-12-15',
        ]);
        $notificacao = new StatusDoPedidoAlterado($pedido, StatusPedido::Aprovado);

        $email = $notificacao->toMail($pedido->usuario);
        $this->assertSame('Seu pedido de viagem para Lisboa, Portugal foi aprovado', $email->subject);
        $this->assertSame('Olá, Ana Souza!', $email->greeting);
        $this->assertSame("Atenciosamente,\nEquipe de Viagens Corporativas", $email->salutation);

        // O rodapé do e-mail também precisa estar em português.
        $html = (string) $email->render();
        $this->assertStringContainsString('Todos os direitos reservados.', $html);
        $this->assertStringNotContainsString('Regards', $html);

        $this->assertSame([
            'pedido_id' => $pedido->id,
            'status' => 'aprovado',
            'destino' => 'Lisboa, Portugal',
            'data_ida' => '2026-12-10',
            'data_volta' => '2026-12-15',
            'mensagem' => 'Seu pedido de viagem para Lisboa, Portugal foi aprovado.',
        ], $notificacao->toArray($pedido->usuario));
    }

    public function test_pedido_aprovado_nao_pode_ser_cancelado(): void
    {
        // A regra principal do desafio.
        $pedido = PedidoViagem::factory()->aprovado()->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => 'cancelado'])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Este pedido já foi aprovado e, por isso, não pode mais ser cancelado.',
                'status_atual' => 'aprovado',
                'status_solicitado' => 'cancelado',
            ]);

        $this->assertSame(StatusPedido::Aprovado, $pedido->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_pedido_cancelado_nao_pode_ser_aprovado(): void
    {
        $pedido = PedidoViagem::factory()->cancelado()->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => 'aprovado'])
            ->assertUnprocessable();

        $this->assertSame(StatusPedido::Cancelado, $pedido->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_solicitante_nao_pode_alterar_o_status_do_proprio_pedido(): void
    {
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs($pedido->usuario);

        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => 'aprovado'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Somente administradores podem aprovar ou cancelar pedidos de viagem.');

        $this->assertSame(StatusPedido::Solicitado, $pedido->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_colaborador_nao_pode_alterar_pedido_de_outra_pessoa(): void
    {
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs(Usuario::factory()->create());

        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => 'cancelado'])
            ->assertForbidden();
    }

    public function test_administrador_nao_aprova_a_propria_viagem(): void
    {
        $admin = Usuario::factory()->administrador()->create();
        $pedido = PedidoViagem::factory()->for($admin)->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => 'aprovado'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não pode aprovar ou cancelar o seu próprio pedido de viagem.');
    }

    public function test_permissao_e_checada_antes_da_validacao(): void
    {
        // Quem não pode alterar recebe 403, mesmo mandando um status inválido.
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs($pedido->usuario);

        $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => 'qualquer-coisa'])
            ->assertForbidden();
    }

    public function test_status_precisa_ser_aprovado_ou_cancelado(): void
    {
        $pedido = PedidoViagem::factory()->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        foreach ([null, 'solicitado', 'pendente'] as $status) {
            $this->patchJson("/api/v1/pedidos-viagem/{$pedido->id}/status", ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);
        }
    }

    public function test_pedido_inexistente_devolve_404(): void
    {
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->patchJson('/api/v1/pedidos-viagem/'.str()->uuid7().'/status', ['status' => 'aprovado'])
            ->assertNotFound();
    }
}
