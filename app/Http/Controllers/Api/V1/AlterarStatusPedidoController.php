<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedidosViagem\AlterarStatusPedidoRequest;
use App\Http\Resources\PedidoViagemResource;
use App\Models\PedidoViagem;
use App\Services\PedidoViagemService;

/**
 * PATCH /pedidos-viagem/{id}/status: aprovar ou cancelar.
 *
 * Controller de ação única (invokable) porque mudar status é uma operação de
 * negócio própria, com regra e notificação, e não uma "edição" genérica.
 * Quando chega aqui, a Policy já confirmou que é um administrador e o
 * FormRequest já validou o status.
 */
class AlterarStatusPedidoController extends Controller
{
    public function __invoke(
        AlterarStatusPedidoRequest $request,
        PedidoViagem $pedido,
        PedidoViagemService $pedidos,
    ): PedidoViagemResource {
        $atualizado = $pedidos->alterarStatus($pedido, $request->novoStatus(), $request->user());

        return new PedidoViagemResource($atualizado);
    }
}
