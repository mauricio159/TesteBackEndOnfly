<?php

/*
| Regras ajustáveis dos pedidos de viagem.
|
| Ficam aqui (e não espalhadas no código) para o time de viagens poder mudar
| o prazo pelo .env, sem precisar de deploy de código novo.
*/

return [

    // Depois de quantas horas "solicitado" um pedido é considerado atrasado:
    // aparece destacado na listagem e passa a gerar lembretes aos administradores.
    'alerta_apos_horas' => (int) env('PEDIDO_ALERTA_APOS_HORAS', 48),

    // De quanto em quanto tempo o lembrete de um MESMO pedido pode ser repetido
    // enquanto ninguém decide. Evita encher a caixa de notificações.
    'repetir_lembrete_apos_horas' => (int) env('PEDIDO_REPETIR_LEMBRETE_APOS_HORAS', 24),

];
