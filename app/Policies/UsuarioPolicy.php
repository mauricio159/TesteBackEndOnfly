<?php

namespace App\Policies;

use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * Quem pode gerenciar a equipe (colaboradores e administradores).
 *
 * Só administrador. Num sistema de viagem corporativa, que mexe com dinheiro
 * da empresa, quem decide quem tem acesso é a própria empresa. Por isso não
 * existe cadastro público: o primeiro administrador vem do seeder (ou é
 * criado direto no banco) e, a partir dele, a equipe é cadastrada pela tela.
 */
class UsuarioPolicy
{
    public function gerenciarEquipe(Usuario $usuario): Response
    {
        return $usuario->ehAdministrador()
            ? Response::allow()
            : Response::deny('Somente administradores podem ver e cadastrar usuários.');
    }
}
