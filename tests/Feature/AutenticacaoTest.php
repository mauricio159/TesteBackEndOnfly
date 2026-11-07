<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login, logout e perfil.
 * Não é requisito direto do desafio, mas sem autenticação não teria como
 * saber quem é o solicitante e quem é o administrador.
 */
class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_com_dados_corretos_devolve_token(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Maria Silva', 'senha' => 'senha123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'senha' => 'senha123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'tipo_token', 'usuario' => ['id', 'nome', 'email', 'administrador']])
            ->assertJsonPath('usuario.nome', 'Maria Silva');
    }

    public function test_nao_existe_cadastro_publico(): void
    {
        // Contas são criadas pelo administrador (POST /usuarios), não por quem chega de fora.
        $this->postJson('/api/v1/auth/cadastro', [
            'nome' => 'Pessoa de Fora',
            'email' => 'fora@viagens.test',
            'senha' => 'senha123',
            'senha_confirmacao' => 'senha123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('usuarios', ['email' => 'fora@viagens.test']);
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
