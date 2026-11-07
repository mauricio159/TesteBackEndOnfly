<?php

namespace App\Notifications;

use App\Enums\StatusPedido;
use App\Models\PedidoViagem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Atende o requisito "notificar o solicitante quando o pedido for aprovado
 * ou cancelado".
 *
 * Vai por dois canais:
 *  - mail: e-mail de verdade (em desenvolvimento quem recebe é o Mailpit);
 *  - database: fica gravada na tabela "notifications" e aparece em GET /notificacoes.
 *
 * ShouldQueue: a requisição HTTP só coloca a notificação na fila e responde na
 * hora. Quem envia o e-mail é o container "queue". Se o SMTP estiver lento, a
 * API não sente.
 */
class StatusDoPedidoAlterado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PedidoViagem $pedido,
        public readonly StatusPedido $status,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pedido = $this->pedido;
        $status = mb_strtolower($this->status->rotulo());

        $mensagem = (new MailMessage)
            ->subject("Seu pedido de viagem para {$pedido->destino} foi {$status}")
            ->greeting("Olá, {$pedido->nome_solicitante}!")
            ->line("Seu pedido de viagem para **{$pedido->destino}** foi **{$status}**.")
            ->line('Ida: '.$pedido->data_ida->format('d/m/Y').' · Volta: '.$pedido->data_volta->format('d/m/Y'))
            ->line("Código do pedido: {$pedido->id}");

        return $this->status === StatusPedido::Aprovado
            ? $mensagem->line('Boa viagem!')
            : $mensagem->line('Em caso de dúvida, fale com o time responsável pelas viagens.');
    }

    /**
     * O que fica gravado no canal database (é o que a API devolve em "dados").
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $status = mb_strtolower($this->status->rotulo());

        return [
            'pedido_id' => $this->pedido->id,
            'status' => $this->status->value,
            'destino' => $this->pedido->destino,
            'data_ida' => $this->pedido->data_ida->toDateString(),
            'data_volta' => $this->pedido->data_volta->toDateString(),
            'mensagem' => "Seu pedido de viagem para {$this->pedido->destino} foi {$status}.",
        ];
    }
}
