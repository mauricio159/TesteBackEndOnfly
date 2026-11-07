<?php

namespace App\Http\Requests\Usuarios;

use App\Models\Usuario;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Administrador cadastrando alguém da equipe: um colaborador (que pede
 * viagens) ou outro administrador (que também aprova e cancela).
 */
class CadastrarUsuarioRequest extends FormRequest
{
    /** Quem não é administrador recebe 403 antes mesmo da validação. */
    public function authorize(): Response
    {
        return Gate::inspect('gerenciarEquipe', Usuario::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:usuarios,email'],
            'senha' => ['required', 'string', 'min:8', 'confirmed:senha_confirmacao'],
            // Opcional: sem informar, cria um colaborador (o caso mais comum).
            'administrador' => ['sometimes', 'boolean'],
        ];
    }
}
