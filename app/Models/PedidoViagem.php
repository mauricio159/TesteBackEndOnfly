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
 * @property int|null $decidido_por_id
 * @property Carbon|null $decidido_em
 * @property Carbon|null $ultimo_lembrete_em
 * @property Carbon $criado_em
 * @property Carbon $atualizado_em
 * @property-read Usuario $usuario
 * @property-read Usuario|null $decididoPor
 *
 * "status", "usuario_id" e os campos da decisão ficam fora do fillable: o dono
 * vem do usuário logado e a decisão só acontece pela regra de negócio
 * (mudarStatusPara). Mesmo que alguém mande esses campos no JSON, são ignorados.
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
            'decidido_em' => 'datetime',
            'ultimo_lembrete_em' => 'datetime',
        ];
    }

    /**
     * Quem fez o pedido.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * Administrador que aprovou ou cancelou (vazio enquanto está "solicitado").
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function decididoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'decidido_por_id');
    }

    /**
     * Único caminho para mudar o status. A regra mora aqui (e no enum), e não
     * no controller, para valer de qualquer lugar: API, comando, job, teste.
     *
     * Junto com o novo status, grava QUEM decidiu e QUANDO (auditoria).
     *
     * @throws MudancaDeStatusInvalida
     */
    public function mudarStatusPara(StatusPedido $novo, Usuario $responsavel): void
    {
        if (! $this->status->podeMudarPara($novo)) {
            throw new MudancaDeStatusInvalida($this->status, $novo);
        }

        $this->status = $novo;
        $this->decidido_por_id = $responsavel->id;
        $this->decidido_em = now();
    }

    /**
     * A partir de quando um pedido parado vira "atrasado" (config/pedidos.php).
     */
    public static function limiteDeAtraso(): Carbon
    {
        return now()->subHours(config('pedidos.alerta_apos_horas'));
    }

    /**
     * Atrasado = ainda esperando decisão e criado há mais tempo que o limite.
     * Pedido aprovado ou cancelado nunca está atrasado, já foi resolvido.
     */
    public function estaAtrasado(): bool
    {
        return $this->status === StatusPedido::Solicitado
            && $this->criado_em->lessThanOrEqualTo(static::limiteDeAtraso());
    }

    /**
     * Mesma regra de estaAtrasado(), só que direto no banco (para filtrar e
     * para o comando de lembretes, sem carregar tudo em memória).
     */
    #[Scope]
    protected function atrasados(Builder $query): void
    {
        $query->where('status', StatusPedido::Solicitado)
            ->where('criado_em', '<=', static::limiteDeAtraso());
    }

    /**
     * Atrasados que ainda não receberam lembrete, ou cujo último lembrete já
     * é antigo o bastante para repetir.
     */
    #[Scope]
    protected function precisandoDeLembrete(Builder $query): void
    {
        $repetirApos = now()->subHours(config('pedidos.repetir_lembrete_apos_horas'));

        $query->atrasados()->where(function (Builder $q) use ($repetirApos) {
            $q->whereNull('ultimo_lembrete_em')->orWhere('ultimo_lembrete_em', '<=', $repetirApos);
        });
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
            ->when($filtros['viagem_ate'] ?? null, fn (Builder $q, string $data) => $q->whereDate('data_volta', '<=', $data))

            // Só os que estão parados há tempo demais esperando decisão.
            ->when($filtros['atrasados'] ?? false, fn (Builder $q) => $q->atrasados());
    }
}
