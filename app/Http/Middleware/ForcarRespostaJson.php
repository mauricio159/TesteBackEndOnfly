<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Faz a API responder sempre em JSON, mesmo que o cliente esqueça o header
 * "Accept: application/json". Sem isso, um erro 401 ou 422 poderia virar um
 * redirect ou uma página HTML, o que não faz sentido num microsserviço.
 */
class ForcarRespostaJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
