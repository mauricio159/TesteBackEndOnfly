<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Autenticacao\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Login por token (Laravel Sanctum).
 *
 * Fluxo: o cliente faz login, recebe um token e manda esse token em toda
 * requisição no header "Authorization: Bearer <token>". Não há sessão nem
 * cookie, o que combina com um microsserviço consumido por outros sistemas.
 *
 * Não existe cadastro aqui: contas são criadas por um administrador em
 * POST /usuarios (UsuarioController).
 */
class AutenticacaoController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::where('email', $request->validated('email'))->first();

        // A mesma mensagem para "e-mail não existe" e "senha errada", para não
        // entregar a quem está tentando invadir quais e-mails estão cadastrados.
        if (! $usuario || ! Hash::check($request->validated('senha'), $usuario->senha)) {
            return response()->json(['message' => 'E-mail ou senha inválidos.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'token' => $usuario->createToken('api')->plainTextToken,
            'tipo_token' => 'Bearer',
            'usuario' => new UsuarioResource($usuario),
        ]);
    }

    public function logout(Request $request): Response
    {
        // Revoga só o token desta requisição. Se a pessoa estiver logada em
        // outro lugar (Postman e front, por exemplo), o outro continua valendo.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function perfil(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user());
    }
}
