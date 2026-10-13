<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Limite de requisições da API.
     *
     * O login tem limite próprio, mais estrito, contra tentativa de
     * adivinhação de senha. Este cobre o restante da API e protege de outra
     * coisa: a extração em massa do cadastro.
     *
     * Sem ele, um usuário autenticado — inclusive o perfil de leitura, que
     * só deveria consultar — poderia percorrer toda a base em segundos,
     * levando CPF, telefone e endereço de cada cliente. A autorização
     * impede alterar; o limite é o que torna a leitura em escala
     * perceptível e custosa.
     *
     * 120 por minuto é folgado para uso humano: a interface agrupa a
     * digitação antes de buscar, e navegar entre páginas e filtros gera
     * pouca dezena de requisições por minuto.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            // Por usuário quando há sessão, por IP quando não há. Só por IP
            // penalizaria todos os usuários atrás de um mesmo endereço;
            // só por usuário não limitaria requisição anônima alguma.
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(120)
                ->by((string) $key)
                // O tipo de retorno é JsonResponse, e não Response:
                // `response()->json()` devolve JsonResponse, que estende a
                // resposta do Symfony e NÃO Illuminate\Http\Response —
                // declarar o tipo errado faz o limitador estourar com 500
                // justamente quando deveria devolver 429.
                ->response(fn (): JsonResponse => response()->json([
                    'message' => 'Muitas requisições. Aguarde um instante e tente novamente.',
                ], HttpStatus::HTTP_TOO_MANY_REQUESTS));
        });
    }
}
