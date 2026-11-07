<?php

namespace App\Http\Requests\Usuarios;

use App\Models\Usuario;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListarUsuariosRequest extends FormRequest
{
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
            // ?perfil=administrador ou ?perfil=colaborador (sem filtro: todos).
            'perfil' => ['sometimes', 'in:administrador,colaborador'],
        ];
    }
}
