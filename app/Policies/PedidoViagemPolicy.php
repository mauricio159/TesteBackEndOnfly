<?php

namespace App\Policies;

use App\Models\PedidoViagem;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * Responde à pergunta "QUEM pode fazer o quê" com os pedidos de viagem.
 *
 * O Laravel encontra esta classe sozinho pelo nome (Model PedidoViagem →
 * PedidoViagemPolicy). Se negar, a API devolve 403 com a mensagem abaixo.
 */
class PedidoViagemPolicy
{
    public function visualizar(Usuario $usuario, PedidoViagem $pedido): Response
    {
        return $usuario->ehAdministrador() || $pedido->usuario_id === $usuario->id
            ? Response::allow()
            : Response::deny('Você só pode consultar os seus próprios pedidos de viagem.');
    }

    /**
     * O desafio diz: só administrador altera o status, e o usuário que fez o
     * pedido não pode alterá-lo. Por isso negamos também o administrador no
     * PRÓPRIO pedido, para ninguém aprovar a própria viagem.
     */
    public function alterarStatus(Usuario $usuario, PedidoViagem $pedido): Response
    {
        if (! $usuario->ehAdministrador()) {
            return Response::deny('Somente administradores podem aprovar ou cancelar pedidos de viagem.');
        }

        if ($pedido->usuario_id === $usuario->id) {
            return Response::deny('Você não pode aprovar ou cancelar o seu próprio pedido de viagem.');
        }

        return Response::allow();
    }
}
