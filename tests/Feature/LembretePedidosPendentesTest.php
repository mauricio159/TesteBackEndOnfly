<?php

namespace Tests\Feature;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use App\Notifications\LembretePedidoPendente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Comando agendado pedidos:lembrar-pendentes: avisa os administradores sobre
 * pedidos parados há mais de 48h, sem repetir o aviso antes de 24h.
 */
class LembretePedidosPendentesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-07 12:00:00');
        config(['pedidos.alerta_apos_horas' => 48, 'pedidos.repetir_lembrete_apos_horas' => 24]);
    }

    public function test_lembra_os_administradores_dos_pedidos_atrasados(): void
    {
        Notification::fake();
        $carla = Usuario::factory()->administrador()->create();
        $diego = Usuario::factory()->administrador()->create();
        $atrasado = PedidoViagem::factory()->aguardandoHa(dias: 3)->create();
        PedidoViagem::factory()->aguardandoHa(dias: 1)->create();             // ainda no prazo
        PedidoViagem::factory()->aguardandoHa(dias: 5)->aprovado(por: $carla)->create(); // já resolvido

        $this->artisan('pedidos:lembrar-pendentes')
            ->expectsOutput('Lembrete enviado para 1 pedido(s) aguardando aprovação.')
            ->assertSuccessful();

        Notification::assertSentTo([$carla, $diego], LembretePedidoPendente::class,
            fn (LembretePedidoPendente $n) => $n->pedido->is($atrasado));
        Notification::assertSentTimes(LembretePedidoPendente::class, 2);
        $this->assertNotNull($atrasado->fresh()->ultimo_lembrete_em);
    }

    public function test_administrador_solicitante_nao_recebe_lembrete_do_proprio_pedido(): void
    {
        // Ele não pode aprovar a própria viagem, então o lembrete vai só para o outro admin.
        Notification::fake();
        $carla = Usuario::factory()->administrador()->create();
        $diego = Usuario::factory()->administrador()->create();
        PedidoViagem::factory()->for($carla)->aguardandoHa(dias: 3)->create();

        $this->artisan('pedidos:lembrar-pendentes')->assertSuccessful();

        Notification::assertSentTo($diego, LembretePedidoPendente::class);
        Notification::assertNotSentTo($carla, LembretePedidoPendente::class);
    }

    public function test_nao_repete_o_lembrete_antes_de_24_horas(): void
    {
        Notification::fake();
        Usuario::factory()->administrador()->create();
        $pedido = PedidoViagem::factory()->aguardandoHa(dias: 3)->create();

        $this->artisan('pedidos:lembrar-pendentes');
        $this->travel(23)->hours();
        $this->artisan('pedidos:lembrar-pendentes')
            ->expectsOutput('Nenhum pedido atrasado precisando de lembrete agora.');
        Notification::assertSentTimes(LembretePedidoPendente::class, 1);

        // Passadas 24h e ainda sem decisão: lembra de novo.
        $this->travel(2)->hours();
        $this->artisan('pedidos:lembrar-pendentes');
        Notification::assertSentTimes(LembretePedidoPendente::class, 2);

        // Registrar o lembrete não conta como "alteração" do pedido.
        $this->assertEquals($pedido->atualizado_em, $pedido->fresh()->atualizado_em);
    }

    public function test_lembrete_aparece_nas_notificacoes_do_administrador(): void
    {
        $admin = Usuario::factory()->administrador()->create();
        $ana = Usuario::factory()->create(['nome' => 'Ana Souza']);
        $pedido = PedidoViagem::factory()->for($ana)->aguardandoHa(dias: 3, horasExtras: 2)
            ->create(['destino' => 'Recife, PE']);

        $this->artisan('pedidos:lembrar-pendentes');

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/notificacoes')
            ->assertOk()
            ->assertJsonPath('data.0.dados.tipo', 'lembrete_pendente')
            ->assertJsonPath('data.0.dados.pedido_id', $pedido->id)
            ->assertJsonPath('data.0.dados.mensagem', 'O pedido de Ana Souza para Recife, PE aguarda aprovação há 3 dias e 2 horas.');
    }

    public function test_comando_esta_agendado_de_hora_em_hora(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pedidos:lembrar-pendentes')
            ->assertSuccessful();
    }
}
