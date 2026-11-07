<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Limite geral da API: 60 requisições por minuto por usuário logado
        // (ou por IP, quando ainda não há login). Protege o serviço de abuso.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Login: limite bem menor, por e-mail + IP, para dificultar
        // quem tenta adivinhar senhas na base da tentativa e erro.
        RateLimiter::for('autenticacao', function (Request $request) {
            return Limit::perMinute(10)->by($request->string('email')->lower().'|'.$request->ip());
        });
    }
}
