<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuários do sistema: quem pede viagem (colaborador) e quem aprova (administrador).
 *
 * A migration padrão do Laravel criava "users", "password_reset_tokens" e
 * "sessions". Como a API usa só token (Sanctum) e não tem tela de "esqueci
 * minha senha", deixei apenas o que o desafio realmente precisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('senha');

            // O desafio diz que só administrador pode aprovar/cancelar pedidos.
            // Um booleano resolve; se um dia surgirem mais papéis, vira tabela própria.
            $table->boolean('administrador')->default(false);

            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
