<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A tabela "notifications" é do próprio Laravel (colunas em inglês). Aqui
 * traduzimos para o mesmo padrão em português do resto da API.
 *
 * @mixin DatabaseNotification
 */
class NotificacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dados' => $this->data,
            'lida_em' => $this->read_at?->toIso8601String(),
            'criada_em' => $this->created_at?->toIso8601String(),
        ];
    }
}
