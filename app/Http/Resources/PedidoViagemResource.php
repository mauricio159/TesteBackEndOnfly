<?php

namespace App\Http\Resources;

use App\Models\PedidoViagem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formato JSON público de um pedido de viagem.
 *
 * Ter essa camada separa "como está no banco" de "o que a API promete" para
 * quem a consome. Dá para renomear uma coluna sem quebrar o front-end.
 *
 * @mixin PedidoViagem
 */
class PedidoViagemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'solicitante' => [
                'id' => $this->usuario_id,
                'nome' => $this->nome_solicitante,
            ],
            'destino' => $this->destino,
            'data_ida' => $this->data_ida->toDateString(),
            'data_volta' => $this->data_volta->toDateString(),
            'status' => $this->status->value,
            'status_rotulo' => $this->status->rotulo(),
            'criado_em' => $this->criado_em?->toIso8601String(),
            'atualizado_em' => $this->atualizado_em?->toIso8601String(),
        ];
    }
}
