<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria da decisão e controle dos lembretes.
 *
 * Migration nova (em vez de editar a que cria a tabela) porque é assim que se
 * evolui um banco que já está em uso: quem já tem dados roda só esta parte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_viagem', function (Blueprint $table) {
            // QUEM aprovou ou cancelou e QUANDO. Como aprovado e cancelado são
            // status finais, cada pedido tem no máximo uma decisão, então duas
            // colunas bastam (não precisamos de uma tabela de histórico).
            $table->foreignId('decidido_por_id')->nullable()->after('status')
                ->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('decidido_em')->nullable()->after('decidido_por_id');

            // Quando o último lembrete de "pedido parado" foi enviado aos
            // administradores. Serve para não mandar o mesmo lembrete toda hora.
            $table->timestamp('ultimo_lembrete_em')->nullable()->after('decidido_em');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_viagem', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decidido_por_id');
            $table->dropColumn(['decidido_em', 'ultimo_lembrete_em']);
        });
    }
};
