<?php

namespace Tests\Unit;

use App\Enums\StatusPedido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Teste unitário "puro" (sem Laravel e sem banco) da regra de mudança de status.
 * Roda em milissegundos e cobre todas as combinações possíveis.
 */
class StatusPedidoTest extends TestCase
{
    /**
     * As 9 combinações de "status atual → status desejado" e se cada uma é permitida.
     *
     * @return array<string, array{StatusPedido, StatusPedido, bool}>
     */
    public static function mudancas(): array
    {
        $solicitado = StatusPedido::Solicitado;
        $aprovado = StatusPedido::Aprovado;
        $cancelado = StatusPedido::Cancelado;

        return [
            'solicitado → aprovado' => [$solicitado, $aprovado, true],
            'solicitado → cancelado' => [$solicitado, $cancelado, true],
            'solicitado → solicitado' => [$solicitado, $solicitado, false],
            'aprovado → cancelado (regra do desafio)' => [$aprovado, $cancelado, false],
            'aprovado → solicitado' => [$aprovado, $solicitado, false],
            'aprovado → aprovado' => [$aprovado, $aprovado, false],
            'cancelado → aprovado' => [$cancelado, $aprovado, false],
            'cancelado → solicitado' => [$cancelado, $solicitado, false],
            'cancelado → cancelado' => [$cancelado, $cancelado, false],
        ];
    }

    #[DataProvider('mudancas')]
    public function test_regras_de_mudanca_de_status(StatusPedido $atual, StatusPedido $desejado, bool $permitido): void
    {
        $this->assertSame($permitido, $atual->podeMudarPara($desejado));
    }

    public function test_rotulos_para_exibicao(): void
    {
        $this->assertSame('Solicitado', StatusPedido::Solicitado->rotulo());
        $this->assertSame('Aprovado', StatusPedido::Aprovado->rotulo());
        $this->assertSame('Cancelado', StatusPedido::Cancelado->rotulo());
    }
}
