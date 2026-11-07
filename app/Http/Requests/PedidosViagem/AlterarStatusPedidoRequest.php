<?php

namespace App\Http\Requests\PedidosViagem;

use App\Enums\StatusPedido;
use App\Models\PedidoViagem;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AlterarStatusPedidoRequest extends FormRequest
{
    /**
     * O Laravel roda a autorização ANTES da validação. Quem não tem permissão
     * recebe 403 direto (com a mensagem da Policy), sem nem olhar o corpo.
     */
    public function authorize(): Response
    {
        /** @var PedidoViagem $pedido */
        $pedido = $this->route('pedido');

        return Gate::inspect('alterarStatus', $pedido);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Pela API só faz sentido aprovar ou cancelar. Voltar para
            // "solicitado" não é uma ação de negócio.
            'status' => [
                'required',
                Rule::enum(StatusPedido::class)->only([StatusPedido::Aprovado, StatusPedido::Cancelado]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'O status deve ser "aprovado" ou "cancelado".',
        ];
    }

    public function novoStatus(): StatusPedido
    {
        return $this->enum('status', StatusPedido::class);
    }
}
