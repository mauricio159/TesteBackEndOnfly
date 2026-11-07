<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuarios\CadastrarUsuarioRequest;
use App\Http\Requests\Usuarios\ListarUsuariosRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Equipe: o administrador lista e cadastra colaboradores e administradores.
 */
class UsuarioController extends Controller
{
    /** GET /usuarios: administradores primeiro, depois em ordem alfabética. */
    public function index(ListarUsuariosRequest $request): AnonymousResourceCollection
    {
        $usuarios = Usuario::query()
            ->when($request->validated('perfil'), fn ($q, string $perfil) => $q->where('administrador', $perfil === 'administrador'))
            ->orderByDesc('administrador')
            ->orderBy('nome')
            ->get();

        return UsuarioResource::collection($usuarios);
    }

    /** POST /usuarios */
    public function store(CadastrarUsuarioRequest $request): JsonResponse
    {
        // "administrador" não é fillable (proteção contra mass assignment).
        // Aqui ele é definido de propósito, num fluxo que só um administrador
        // já autenticado consegue usar.
        $usuario = new Usuario($request->safe()->only(['nome', 'email', 'senha']));
        $usuario->administrador = $request->boolean('administrador');
        $usuario->save();

        // Sem token na resposta: quem cadastra é o administrador, e a pessoa
        // cadastrada entra depois com o e-mail e a senha inicial combinados.
        return UsuarioResource::make($usuario)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
