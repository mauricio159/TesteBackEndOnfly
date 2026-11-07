<?php

namespace App\Console\Commands;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use App\Notifications\LembretePedidoPendente;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Procura pedidos parados há tempo demais e lembra os administradores.
 *
 * Roda sozinho de hora em hora (routes/console.php + container "scheduler"),
 * mas também dá para rodar na mão:
 *   docker compose exec app php artisan pedidos:lembrar-pendentes
 *
 * Regras:
 *  - só pedidos "solicitado" criados há mais de PEDIDO_ALERTA_APOS_HORAS;
 *  - o lembrete vai para os administradores, menos o próprio solicitante
 *    (ele não pode aprovar a própria viagem, então o aviso seria inútil);
 *  - o mesmo pedido só é lembrado de novo depois de PEDIDO_REPETIR_LEMBRETE_APOS_HORAS.
 */
#[Signature('pedidos:lembrar-pendentes')]
#[Description('Envia lembrete aos administradores sobre pedidos aguardando aprovação há muito tempo')]
class LembrarPedidosPendentes extends Command
{
    public function handle(): int
    {
        $administradores = Usuario::where('administrador', true)->get();
        $lembrados = 0;

        // lazyById: percorre em lotes, sem carregar milhares de pedidos de uma vez.
        PedidoViagem::query()->precisandoDeLembrete()->lazyById(100)->each(
            function (PedidoViagem $pedido) use ($administradores, &$lembrados) {
                $destinatarios = $administradores->reject(fn (Usuario $admin) => $admin->id === $pedido->usuario_id);

                if ($destinatarios->isEmpty()) {
                    $this->warn("Pedido {$pedido->id}: nenhum outro administrador para aprovar.");

                    return;
                }

                Notification::send($destinatarios, new LembretePedidoPendente($pedido));

                // Marca o lembrete sem mexer em "atualizado_em": lembrar não é alterar o pedido.
                $pedido->timestamps = false;
                $pedido->ultimo_lembrete_em = now();
                $pedido->save();

                $lembrados++;
            }
        );

        $this->info($lembrados
            ? "Lembrete enviado para {$lembrados} pedido(s) aguardando aprovação."
            : 'Nenhum pedido atrasado precisando de lembrete agora.');

        return self::SUCCESS;
    }
}
