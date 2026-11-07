<?php

use Illuminate\Support\Facades\Route;

// Este serviço é só API. A raiz apenas se apresenta e aponta onde estão
// a API e o health check, útil para quem abre http://localhost:8000 no navegador.
Route::get('/', fn () => response()->json([
    'servico' => config('app.name'),
    'api' => url('/api/v1'),
    'saude' => url('/up'),
]));
