<?php

use App\Http\Controllers\Api\V1\AlterarStatusPedidoController;
use App\Http\Controllers\Api\V1\AutenticacaoController;
use App\Http\Controllers\Api\V1\NotificacaoController;
use App\Http\Controllers\Api\V1\PedidoViagemController;
use Illuminate\Support\Facades\Route;

/*
| Todas as rotas abaixo ficam em /api/v1/...
|
| O "/api" o Laravel coloca sozinho; o "/v1" é nosso. Versionar a API deixa
| lançar uma v2 no futuro sem quebrar quem já consome a v1.
*/
Route::prefix('v1')->name('v1.')->group(function () {

    // Autenticação. Cadastro e login têm limite próprio de tentativas
    // (veja AppServiceProvider) para dificultar ataque de força bruta.
    Route::prefix('auth')->name('auth.')->controller(AutenticacaoController::class)->group(function () {
        Route::post('cadastro', 'cadastrar')->middleware('throttle:autenticacao')->name('cadastro');
        Route::post('login', 'login')->middleware('throttle:autenticacao')->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', 'logout')->name('logout');
            Route::get('perfil', 'perfil')->name('perfil');
        });
    });

    // Daqui para baixo, só com token válido.
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('pedidos-viagem', PedidoViagemController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['pedidos-viagem' => 'pedido']);

        Route::patch('pedidos-viagem/{pedido}/status', AlterarStatusPedidoController::class)
            ->name('pedidos-viagem.status');

        Route::get('notificacoes', NotificacaoController::class)->name('notificacoes');
    });
});
