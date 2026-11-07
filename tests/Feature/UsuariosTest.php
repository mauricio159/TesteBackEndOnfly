<?php

namespace Tests\Feature;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Equipe: só o administrador cadastra colaboradores e outros administradores.
 * Não existe cadastro público; quem decide quem tem acesso é a empresa.
 */
class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extras = []): array
    {
        return [
            'nome' => 'Maria Silva',
            'email' => 'maria@viagens.test',
            'senha' => 'senha123',
            'senha_confirmacao' => 'senha123',
            ...$extras,
        ];
    }

    public function test_administrador_cadastra_colaborador(): void
    {
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->postJson('/api/v1/usuarios', $this->dados())
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Maria Silva')
            ->assertJsonPath('data.administrador', false) // sem informar, é colaborador
            ->assertJsonMissingPath('token');              // a pessoa faz o próprio login

        $this->assertFalse(Usuario::where('email', 'maria@viagens.test')->first()->ehAdministrador());
    }

    public function test_administrador_cadastra_outro_administrador(): void
    {
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->postJson('/api/v1/usuarios', $this->dados(['nome' => 'Diego Ramos', 'administrador' => true]))
            ->assertCreated()
            ->assertJsonPath('data.administrador', true);
    }

    public function test_colaborador_cadastrado_consegue_entrar_e_pedir_viagem(): void
    {
        Sanctum::actingAs(Usuario::factory()->administrador()->create());
        $this->postJson('/api/v1/usuarios', $this->dados())->assertCreated();

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'maria@viagens.test', 'senha' => 'senha123'])
            ->assertOk()
            ->json('token');

        $this->app['auth']->forgetGuards(); // descarta o login do administrador
        $this->withToken($token)
            ->postJson('/api/v1/pedidos-viagem', [
                'destino' => 'Recife, PE',
                'data_ida' => now()->addWeek()->toDateString(),
                'data_volta' => now()->addWeeks(2)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.solicitante.nome', 'Maria Silva');
    }

    public function test_colaborador_nao_cadastra_ninguem(): void
    {
        Sanctum::actingAs(Usuario::factory()->create());

        $this->postJson('/api/v1/usuarios', $this->dados(['administrador' => true]))
            ->assertForbidden()
            ->assertJsonPath('message', 'Somente administradores podem ver e cadastrar usuários.');

        $this->assertDatabaseMissing('usuarios', ['email' => 'maria@viagens.test']);
    }

    public function test_cadastro_valida_os_dados_com_mensagens_em_portugues(): void
    {
        Usuario::factory()->create(['email' => 'ja.existe@viagens.test']);
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->postJson('/api/v1/usuarios', [
            'nome' => '',
            'email' => 'ja.existe@viagens.test',
            'senha' => '123',
            'administrador' => 'talvez',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'email', 'senha', 'administrador'])
            ->assertJsonPath('errors.nome.0', 'O campo nome é obrigatório.')
            ->assertJsonPath('errors.email.0', 'Este e-mail já está em uso.');
    }

    public function test_lista_a_equipe_com_administradores_primeiro(): void
    {
        Usuario::factory()->create(['nome' => 'Bruno Lima']);
        Usuario::factory()->create(['nome' => 'Ana Souza']);
        Usuario::factory()->administrador()->create(['nome' => 'Diego Ramos']);
        Sanctum::actingAs(Usuario::factory()->administrador()->create(['nome' => 'Carla Mendes']));

        $nomes = $this->getJson('/api/v1/usuarios')->assertOk()->json('data.*.nome');

        $this->assertSame(['Carla Mendes', 'Diego Ramos', 'Ana Souza', 'Bruno Lima'], $nomes);
    }

    public function test_filtra_a_equipe_por_perfil(): void
    {
        Usuario::factory()->count(2)->create();
        Sanctum::actingAs(Usuario::factory()->administrador()->create());

        $this->getJson('/api/v1/usuarios?perfil=colaborador')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/usuarios?perfil=administrador')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/usuarios?perfil=chefe')->assertUnprocessable();
    }

    public function test_colaborador_nao_ve_a_equipe(): void
    {
        Sanctum::actingAs(Usuario::factory()->create());

        $this->getJson('/api/v1/usuarios')->assertForbidden();
    }

    public function test_novo_administrador_aprova_a_viagem_de_quem_o_cadastrou(): void
    {
        // A Carla (admin) pede uma viagem, cadastra o Diego como admin, e é ele quem aprova.
        $carla = Usuario::factory()->administrador()->create();
        $pedidoDaCarla = PedidoViagem::factory()->for($carla)->create();

        Sanctum::actingAs($carla);
        $this->postJson('/api/v1/usuarios', $this->dados([
            'nome' => 'Diego Ramos', 'email' => 'diego@viagens.test', 'administrador' => true,
        ]))->assertCreated();
        $this->patchJson("/api/v1/pedidos-viagem/{$pedidoDaCarla->id}/status", ['status' => 'aprovado'])
            ->assertForbidden(); // a própria Carla continua não podendo

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'diego@viagens.test', 'senha' => 'senha123'])->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->patchJson("/api/v1/pedidos-viagem/{$pedidoDaCarla->id}/status", ['status' => 'aprovado'])
            ->assertOk()
            ->assertJsonPath('data.decisao.por.nome', 'Diego Ramos');
    }
}
