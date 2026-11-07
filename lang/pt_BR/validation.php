<?php

/*
| Mensagens de validação em português.
|
| O Laravel vem só com inglês. Traduzi as regras que a API usa (e mais algumas
| comuns). Qualquer regra que não estiver aqui cai no inglês automaticamente,
| porque APP_FALLBACK_LOCALE=en.
*/

return [
    'accepted' => 'O campo :attribute deve ser aceito.',
    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'before' => 'O campo :attribute deve ser uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute deve ser uma data igual ou anterior a :date.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'date_format' => 'O campo :attribute deve estar no formato :format (ex.: 2026-12-31).',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'enum' => 'O valor informado em :attribute é inválido.',
    'exists' => 'O valor informado em :attribute não existe.',
    'in' => 'O valor informado em :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O campo :attribute não pode ter mais de :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O campo :attribute deve ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está em uso.',

    /*
    | Nomes "humanos" dos campos. Sem isso a mensagem sairia
    | "O campo data_ida é obrigatório"; com isso, "O campo data de ida é obrigatório".
    */
    'attributes' => [
        'nome' => 'nome',
        'email' => 'e-mail',
        'senha' => 'senha',
        'destino' => 'destino',
        'data_ida' => 'data de ida',
        'data_volta' => 'data de volta',
        'status' => 'status',
        'administrador' => 'administrador',
        'perfil' => 'perfil',
        'criado_de' => 'início do período de criação',
        'criado_ate' => 'fim do período de criação',
        'viagem_de' => 'início do período da viagem',
        'viagem_ate' => 'fim do período da viagem',
        'por_pagina' => 'itens por página',
        'pagina' => 'página',
    ],
];
