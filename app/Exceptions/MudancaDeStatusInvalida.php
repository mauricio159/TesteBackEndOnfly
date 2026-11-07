<?php

namespace App\Exceptions;

use App\Enums\StatusPedido;
use DomainException;

/**
 * Lançada quando alguém tenta uma mudança de status proibida pela regra
 * de negócio. O caso mais importante é o que o desafio cita: cancelar um
 * pedido que já foi aprovado.
 *
 * É uma exceção de domínio e não sabe nada de HTTP. Quem transforma isso
 * em resposta 422 é o bootstrap/app.php.
 */
class MudancaDeStatusInvalida extends DomainException
{
    public function __construct(
        public readonly StatusPedido $statusAtual,
        public readonly StatusPedido $statusDesejado,
    ) {
        parent::__construct(match (true) {
            $statusAtual === StatusPedido::Aprovado && $statusDesejado === StatusPedido::Cancelado => 'Este pedido já foi aprovado e, por isso, não pode mais ser cancelado.',
            $statusAtual === $statusDesejado => "O pedido já está com o status \"{$statusAtual->rotulo()}\".",
            default => "Não é possível mudar o status de \"{$statusAtual->rotulo()}\" para \"{$statusDesejado->rotulo()}\".",
        });
    }
}
