<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedidosViagem\CriarPedidoViagemRequest;
use App\Http\Requests\PedidosViagem\ListarPedidosViagemRequest;
use App\Http\Resources\PedidoViagemResource;
use App\Models\PedidoViagem;
use App\Services\PedidoViagemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Criar, consultar e listar pedidos de viagem.
 *
 * Controller enxuto de propósito: recebe a requisição já validada, chama o
 * domínio/serviço e devolve um Resource. Nenhuma regra de negócio mora aqui.
 *
 * Os nomes index/store/show são convenção do Laravel para rotas de recurso,
 * por isso ficaram em inglês.
 */
class PedidoViagemController extends Controller
{
    public function __construct(
        private readonly PedidoViagemService $pedidos,
    ) {}

    /** GET /pedidos-viagem: lista com filtros e paginação. */
    public function index(ListarPedidosViagemRequest $request): AnonymousResourceCollection
    {
        $pedidos = PedidoViagem::query()
            ->visivelPara($request->user())
            ->filtrar($request->validated())
            // Carrega quem decidiu de uma vez só (evita uma consulta por linha, o famoso N+1).
            ->with('decididoPor')
            ->latest()
            ->paginate(perPage: $request->integer('por_pagina', 15), pageName: 'pagina')
            ->withQueryString();

        return PedidoViagemResource::collection($pedidos);
    }

    /** POST /pedidos-viagem: cria um pedido em nome do usuário logado. */
    public function store(CriarPedidoViagemRequest $request): JsonResponse
    {
        $pedido = $this->pedidos->criar($request->user(), $request->validated());

        return PedidoViagemResource::make($pedido)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /** GET /pedidos-viagem/{id}: consulta um pedido. */
    public function show(PedidoViagem $pedido): PedidoViagemResource
    {
        Gate::authorize('visualizar', $pedido);

        return new PedidoViagemResource($pedido);
    }
}
