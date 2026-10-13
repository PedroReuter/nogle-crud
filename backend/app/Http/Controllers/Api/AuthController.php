<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Sessão do usuário.
 *
 * Autenticação por sessão com cookie, não por token: o cookie é HttpOnly e
 * portanto inacessível a JavaScript, o que atende ao requisito de não
 * guardar credencial no localStorage e elimina a via mais comum de roubo de
 * credencial por XSS.
 */
final class AuthController
{
    /**
     * POST /api/login
     */
    public function login(LoginRequest $request): UserResource
    {
        $request->authenticate();

        // Troca o identificador da sessão após o login.
        //
        // É a defesa contra fixação de sessão: sem isso, um identificador
        // que o atacante tenha conseguido plantar no navegador da vítima
        // antes do login continuaria válido depois dele, dando ao atacante
        // uma sessão autenticada.
        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        // Invalidar descarta os dados da sessão; regenerar o token de CSRF
        // impede que o token antigo continue sendo aceito.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * GET /api/me
     *
     * Usado pelo SPA ao carregar, para descobrir se já existe sessão ativa e
     * qual o perfil — sem isso a interface precisaria guardar esse estado no
     * cliente, que é justamente o que a sessão por cookie evita.
     */
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
