<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro, login, logout e perfil.
 * Não é requisito direto do desafio, mas sem autenticação não teria como
 * saber quem é o solicitante e quem é o administrador.
 */
class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_colaborador_se_cadastra_e_recebe_um_token(): void
    {
        $this->postJson('/api/v1/auth/cadastro', [
            'nome' => 'Maria Silva',
            'email' => 'maria@viagens.test',
            'senha' => 'senha123',
            'senha_confirmacao' => 'senha123',
        ])
            ->assertCreated()
            ->assertJsonStructure(['token', 'tipo_token', 'usuario' => ['id', 'nome', 'email', 'administrador']])
            ->assertJsonPath('usuario.nome', 'Maria Silva')
            ->assertJsonPath('usuario.administrador', false);
    }

    public function test_cadastro_nao_permite_se_tornar_administrador(): void
    {
        // Tentativa de "mass assignment": mandar administrador=true no corpo.
        $this->postJson('/api/v1/auth/cadastro', [
            'nome' => 'Espertinho',
            'email' => 'espertinho@viagens.test',
            'senha' => 'senha123',
            'senha_confirmacao' => 'senha123',
            'administrador' => true,
        ])->assertCreated();

        $this->assertFalse(Usuario::where('email', 'espertinho@viagens.test')->first()->ehAdministrador());
    }

    public function test_cadastro_valida_os_dados_com_mensagens_em_portugues(): void
    {
        Usuario::factory()->create(['email' => 'ja.existe@viagens.test']);

        $this->postJson('/api/v1/auth/cadastro', [
            'nome' => '',
            'email' => 'ja.existe@viagens.test',
            'senha' => '123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'email', 'senha'])
            ->assertJsonPath('errors.nome.0', 'O campo nome é obrigatório.')
            ->assertJsonPath('errors.email.0', 'Este e-mail já está em uso.');
    }

    public function test_login_com_dados_corretos_devolve_token(): void
    {
        $usuario = Usuario::factory()->create(['senha' => 'senha123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'senha' => 'senha123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_com_senha_errada_e_recusado(): void
    {
        $usuario = Usuario::factory()->create(['senha' => 'senha123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'senha' => 'senha-errada',
        ])->assertUnauthorized()->assertJson(['message' => 'E-mail ou senha inválidos.']);
    }

    public function test_perfil_devolve_o_usuario_logado(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('teste')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/perfil')
            ->assertOk()
            ->assertJsonPath('data.id', $usuario->id);
    }

    public function test_logout_invalida_o_token_usado(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('teste')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_rotas_protegidas_exigem_login(): void
    {
        $this->getJson('/api/v1/pedidos-viagem')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Não autenticado. Faça login e envie o token no header Authorization: Bearer <token>.');

        $this->getJson('/api/v1/auth/perfil')->assertUnauthorized();
    }
}
