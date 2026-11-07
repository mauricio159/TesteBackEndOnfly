<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificacaoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /notificacoes: avisos recebidos pelo usuário logado (pedido aprovado ou
 * cancelado). É o que o front mostra, sem precisar abrir o e-mail.
 */
class NotificacaoController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return NotificacaoResource::collection(
            $request->user()->notifications()->paginate(perPage: 15, pageName: 'pagina')
        );
    }
}
