<?php

namespace App\Notifications;

use App\Models\PedidoViagem;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Lembrete para os administradores: "este pedido está parado há tempo demais".
 *
 * Enviado pelo comando pedidos:lembrar-pendentes, que o agendador roda de hora
 * em hora. Vai só pelo canal database (aparece em Notificações): com vários
 * pedidos parados, mandar um e-mail por pedido viraria spam. Se o time quiser
 * e-mail também, basta acrescentar 'mail' no via() e criar o toMail().
 */
class LembretePedidoPendente extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PedidoViagem $pedido,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $pedido = $this->pedido;
        // "3 dias e 4 horas" (sem o "há", que já está na frase).
        $tempo = $pedido->criado_em->locale('pt_BR')->diffForHumans(['parts' => 2, 'join' => true, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]);

        return [
            'tipo' => 'lembrete_pendente',
            'pedido_id' => $pedido->id,
            'status' => $pedido->status->value,
            'destino' => $pedido->destino,
            'solicitante' => $pedido->nome_solicitante,
            'aguardando_desde' => $pedido->criado_em->toIso8601String(),
            'mensagem' => "O pedido de {$pedido->nome_solicitante} para {$pedido->destino} aguarda aprovação há {$tempo}.",
        ];
    }
}
