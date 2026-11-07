<?php

namespace App\Services;

use App\Enums\StatusPedido;
use App\Exceptions\MudancaDeStatusInvalida;
use App\Models\PedidoViagem;
use App\Models\Usuario;
use App\Notifications\StatusDoPedidoAlterado;
use Illuminate\Support\Facades\DB;

/**
 * Casos de uso dos pedidos de viagem: criar e alterar status.
 *
 * O controller só traduz HTTP para a aplicação e de volta. A orquestração
 * (transação, trava no banco, notificação) fica aqui, e pode ser
 * reaproveitada por um comando artisan ou um job, por exemplo.
 */
class PedidoViagemService
{
    /**
     * @param  array{destino: string, data_ida: string, data_volta: string}  $dados
     */
    public function criar(Usuario $solicitante, array $dados): PedidoViagem
    {
        // O nome do solicitante vem SEMPRE do usuário logado, nunca do corpo
        // da requisição. Assim ninguém abre pedido em nome de outra pessoa.
        return $solicitante->pedidosViagem()->create([
            ...$dados,
            'nome_solicitante' => $solicitante->nome,
        ]);
    }

    /**
     * Aprova ou cancela um pedido, registra quem decidiu e avisa quem pediu.
     *
     * @throws MudancaDeStatusInvalida
     */
    public function alterarStatus(PedidoViagem $pedido, StatusPedido $novoStatus, Usuario $responsavel): PedidoViagem
    {
        $atualizado = DB::transaction(function () use ($pedido, $novoStatus, $responsavel) {
            // SELECT ... FOR UPDATE: imagine dois administradores clicando ao
            // mesmo tempo, um em "aprovar" e outro em "cancelar". O segundo
            // espera o primeiro terminar, lê o status já atualizado e cai na
            // regra de negócio, em vez de sobrescrever a decisão do colega.
            $travado = PedidoViagem::query()->lockForUpdate()->findOrFail($pedido->getKey());

            $travado->mudarStatusPara($novoStatus, $responsavel);
            $travado->save();

            return $travado;
        });

        // Já deixamos carregado quem decidiu: a resposta da API e a notificação mostram o nome.
        $atualizado->setRelation('decididoPor', $responsavel);

        // Notificamos só DEPOIS do commit: se algo desse errado e a transação
        // fosse desfeita, o colaborador não receberia um aviso falso.
        // O envio em si vai para a fila (a notificação implementa ShouldQueue).
        $atualizado->usuario->notify(new StatusDoPedidoAlterado($atualizado, $novoStatus));

        return $atualizado;
    }
}
