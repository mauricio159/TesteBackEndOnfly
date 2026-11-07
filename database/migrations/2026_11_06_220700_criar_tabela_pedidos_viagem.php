<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela principal do desafio: os pedidos de viagem corporativa.
 *
 * Campos pedidos no enunciado: ID, nome do solicitante, destino,
 * data de ida, data de volta e status (solicitado, aprovado, cancelado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_viagem', function (Blueprint $table) {
            // UUID em vez de 1, 2, 3...: quem usa a API não consegue "chutar"
            // o pedido do colega nem descobrir quantos pedidos a empresa tem.
            $table->uuid('id')->primary();

            // Quem fez o pedido. restrictOnDelete impede apagar um usuário
            // que tenha viagens, para não sumir com o histórico.
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();

            // Guardamos o nome como estava no dia do pedido. Se a pessoa mudar
            // o nome no cadastro depois, o histórico continua fiel.
            $table->string('nome_solicitante');
            $table->string('destino');
            $table->date('data_ida');
            $table->date('data_volta');

            // String + Enum no PHP (em vez do ENUM do MySQL): criar um status
            // novo no futuro não exige ALTER TABLE em produção.
            $table->string('status', 20)->default('solicitado');

            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            // Índices pensados para os filtros da listagem
            // (status, destino, período da viagem e período de criação).
            $table->index('status');
            $table->index('destino');
            $table->index(['data_ida', 'data_volta']);
            $table->index('criado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_viagem');
    }
};
