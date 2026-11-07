<?php

use App\Exceptions\MudancaDeStatusInvalida;
use App\Http\Middleware\ForcarRespostaJson;
use App\Models\PedidoViagem;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [ForcarRespostaJson::class]);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Na API, qualquer erro sai em JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Tentar cancelar um pedido aprovado é um "não" esperado, não um bug.
        // Por isso não vai para o log de erros (e não dispararia alerta).
        $exceptions->dontReport(MudancaDeStatusInvalida::class);

        // Regra de negócio violada → 422, dizendo o status atual e o pedido.
        $exceptions->render(function (MudancaDeStatusInvalida $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status_atual' => $e->statusAtual->value,
                'status_solicitado' => $e->statusDesejado->value,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // Sem token (ou token inválido) → 401 com uma mensagem que ajuda.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Não autenticado. Faça login e envie o token no header Authorization: Bearer <token>.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        });

        // Passou do limite de requisições → 429, mantendo os headers Retry-After.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Muitas requisições em pouco tempo. Aguarde um instante e tente de novo.',
                ], Response::HTTP_TOO_MANY_REQUESTS, $e->getHeaders());
            }
        });

        // ID que não existe → 404 com mensagem amigável. Em modo debug o padrão
        // do Laravel mostraria "No query results for model [App\Models\...]",
        // expondo nomes internos de classes.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $anterior = $e->getPrevious();

            if ($request->is('api/*') && $anterior instanceof ModelNotFoundException) {
                $mensagem = $anterior->getModel() === PedidoViagem::class
                    ? 'Pedido de viagem não encontrado.'
                    : 'Registro não encontrado.';

                return response()->json(['message' => $mensagem], Response::HTTP_NOT_FOUND);
            }
        });
    })->create();
