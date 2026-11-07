<?php

namespace App\Models;

use App\Enums\StatusPedido;
use App\Exceptions\MudancaDeStatusInvalida;
use Database\Factories\PedidoViagemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pedido de viagem corporativa, o coração do desafio.
 *
 * @property string $id
 * @property int $usuario_id
 * @property string $nome_solicitante
 * @property string $destino
 * @property Carbon $data_ida
 * @property Carbon $data_volta
 * @property StatusPedido $status
 * @property Carbon $criado_em
 * @property Carbon $atualizado_em
 * @property-read Usuario $usuario
 *
 * "status" e "usuario_id" ficam fora do fillable: o dono vem do usuário
 * logado e o status só muda pela regra de negócio (mudarStatusPara).
 * Mesmo que alguém mande esses campos no JSON, eles são ignorados.
 */
#[Fillable(['nome_solicitante', 'destino', 'data_ida', 'data_volta'])]
class PedidoViagem extends Model
{
    /** @use HasFactory<PedidoViagemFactory> */
    use HasFactory, HasUuids;

    protected $table = 'pedidos_viagem';

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    /** Todo pedido nasce como "solicitado". */
    protected $attributes = [
        'status' => 'solicitado',
    ];

    protected function casts(): array
    {
        return [
            'data_ida' => 'date',
            'data_volta' => 'date',
            'status' => StatusPedido::class,
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * Único caminho para mudar o status. A regra mora aqui (e no enum), e não
     * no controller, para valer de qualquer lugar: API, comando, job, teste.
     *
     * @throws MudancaDeStatusInvalida
     */
    public function mudarStatusPara(StatusPedido $novo): void
    {
        if (! $this->status->podeMudarPara($novo)) {
            throw new MudancaDeStatusInvalida($this->status, $novo);
        }

        $this->status = $novo;
    }

    /**
     * Administrador enxerga todos os pedidos; colaborador, só os próprios.
     */
    #[Scope]
    protected function visivelPara(Builder $query, Usuario $usuario): void
    {
        if (! $usuario->ehAdministrador()) {
            $query->whereBelongsTo($usuario);
        }
    }

    /**
     * Filtros da listagem pedidos no desafio: status, destino e período
     * (tanto da criação do pedido quanto das datas da viagem).
     *
     * Recebe só dados que o FormRequest já validou.
     *
     * @param  array<string, mixed>  $filtros
     */
    #[Scope]
    protected function filtrar(Builder $query, array $filtros): void
    {
        $query
            ->when($filtros['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))

            // Busca parcial: "lisboa" encontra "Lisboa, Portugal". Escapamos o % e o _
            // para que, se o usuário digitar "%", isso não vire "traga tudo".
            ->when($filtros['destino'] ?? null, function (Builder $q, string $destino) {
                $q->where('destino', 'like', '%'.addcslashes($destino, '%_\\').'%');
            })

            // Período em que o pedido foi FEITO. Comparamos por faixa de horário
            // (início e fim do dia) em vez de whereDate para o índice ser usado.
            ->when($filtros['criado_de'] ?? null, fn (Builder $q, string $data) => $q->where('criado_em', '>=', Carbon::parse($data)->startOfDay()))
            ->when($filtros['criado_ate'] ?? null, fn (Builder $q, string $data) => $q->where('criado_em', '<=', Carbon::parse($data)->endOfDay()))

            // Período da VIAGEM: a viagem inteira precisa caber na faixa
            // (ida depois do início e volta antes do fim).
            ->when($filtros['viagem_de'] ?? null, fn (Builder $q, string $data) => $q->whereDate('data_ida', '>=', $data))
            ->when($filtros['viagem_ate'] ?? null, fn (Builder $q, string $data) => $q->whereDate('data_volta', '<=', $data));
    }
}
