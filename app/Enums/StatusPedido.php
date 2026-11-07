<?php

namespace App\Enums;

/**
 * Os três status que o desafio define para um pedido de viagem.
 *
 * Além de listar os valores, o enum guarda a regra de quais mudanças são
 * permitidas (uma pequena máquina de estados):
 *
 *   solicitado ──► aprovado    (fim da linha)
 *        │
 *        └──────► cancelado   (fim da linha)
 *
 * O enunciado diz que só dá para cancelar um pedido que ainda NÃO foi
 * aprovado. Então, depois de aprovado ou cancelado, o pedido não muda mais.
 */
enum StatusPedido: string
{
    case Solicitado = 'solicitado';
    case Aprovado = 'aprovado';
    case Cancelado = 'cancelado';

    /**
     * Para quais status um pedido pode ir a partir do status atual.
     *
     * @return list<self>
     */
    public function proximosPermitidos(): array
    {
        return match ($this) {
            self::Solicitado => [self::Aprovado, self::Cancelado],
            self::Aprovado, self::Cancelado => [],
        };
    }

    public function podeMudarPara(self $novo): bool
    {
        return in_array($novo, $this->proximosPermitidos(), true);
    }

    /** Texto amigável para telas, e-mails e para o campo "status_rotulo" da API. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Solicitado => 'Solicitado',
            self::Aprovado => 'Aprovado',
            self::Cancelado => 'Cancelado',
        };
    }
}
