<?php

namespace App\Http\Requests\Autenticacao;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro de um novo colaborador. Todo cadastro pela API cria um usuário
 * comum; administradores são definidos direto no banco (ou pelo seeder).
 */
class CadastroRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:usuarios,email'],
            // "confirmed:senha_confirmacao" = o campo senha_confirmacao precisa ser igual.
            'senha' => ['required', 'string', 'min:8', 'confirmed:senha_confirmacao'],
        ];
    }
}
