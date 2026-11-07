<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Pessoa que usa o sistema: colaborador que pede viagens ou administrador
 * que aprova/cancela.
 *
 * @property int $id
 * @property string $nome
 * @property string $email
 * @property string $senha
 * @property bool $administrador
 * @property Carbon $criado_em
 * @property Carbon $atualizado_em
 *
 * "administrador" fica FORA do fillable de propósito. Assim ninguém vira
 * admin mandando {"administrador": true} no cadastro (mass assignment).
 */
#[Fillable(['nome', 'email', 'senha'])]
#[Hidden(['senha'])]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    // Colunas de data em português. O Laravel usa essas constantes em todo
    // lugar (create, update, latest()...), então basta declarar aqui.
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    // Diz ao Laravel que a senha está na coluna "senha" e não em "password".
    protected $authPasswordName = 'senha';

    // Não usamos "lembrar de mim" (a API é só token), então não há coluna para isso.
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            // "hashed": qualquer valor gravado aqui vira bcrypt automaticamente.
            'senha' => 'hashed',
            'administrador' => 'boolean',
        ];
    }

    public function ehAdministrador(): bool
    {
        return $this->administrador === true;
    }

    /**
     * @return HasMany<PedidoViagem, $this>
     */
    public function pedidosViagem(): HasMany
    {
        return $this->hasMany(PedidoViagem::class);
    }
}
