<?php

namespace App\Http\Requests\PedidosViagem;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida a criação de um pedido de viagem.
 *
 * Repare que só aceitamos destino e datas. Nome do solicitante, status e
 * dono do pedido NÃO vêm do cliente: o sistema define tudo isso.
 */
class CriarPedidoViagemRequest extends FormRequest
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
            'destino' => ['required', 'string', 'min:2', 'max:255'],
            // Formato ISO (AAAA-MM-DD), que é o padrão de APIs e evita a
            // confusão entre 10/12 (10 de dezembro) e 12/10 (12 de outubro).
            'data_ida' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'data_volta' => ['required', 'date_format:Y-m-d', 'after_or_equal:data_ida'],
        ];
    }

    /**
     * Mensagens específicas, mais claras que as genéricas do Laravel.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data_ida.after_or_equal' => 'A data de ida não pode estar no passado.',
            'data_volta.after_or_equal' => 'A data de volta deve ser igual ou posterior à data de ida.',
        ];
    }
}
