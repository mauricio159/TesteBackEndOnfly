<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tarefas agendadas. Quem executa é o container "scheduler" (php artisan schedule:work).
*/

// De hora em hora, lembra os administradores dos pedidos parados há tempo demais.
// withoutOverlapping: se uma execução demorar, a próxima não começa por cima dela.
Schedule::command('pedidos:lembrar-pendentes')->hourly()->withoutOverlapping();
