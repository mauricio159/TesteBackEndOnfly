<?php

namespace App\Http\Requests\PedidosViagem;

use App\Enums\StatusPedido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida os filtros da listagem (vêm pela query string).
 *
 * Preferi devolver 422 para filtro inválido em vez de ignorá-lo: se alguém
 * mandar ?status=pendente, é melhor avisar do que devolver "tudo" e dar a
 * impressão de que o filtro funcionou.
 */
class ListarPedidosViagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(StatusPedido::class)],
            'destino' => ['sometimes', 'string', 'max:255'],
            'criado_de' => ['sometimes', 'date_format:Y-m-d'],
            'criado_ate' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:criado_de'],
            'viagem_de' => ['sometimes', 'date_format:Y-m-d'],
            'viagem_ate' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:viagem_de'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'criado_ate.after_or_equal' => 'O fim do período de criação deve ser igual ou posterior ao início.',
            'viagem_ate.after_or_equal' => 'O fim do período da viagem deve ser igual ou posterior ao início.',
        ];
    }
}
