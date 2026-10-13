<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A API responde apenas JSON. Sem isto, uma exceção numa rota de
        // API renderizaria a página de erro HTML do Laravel, que o frontend
        // não sabe interpretar — e que, com APP_DEBUG ligado, exibiria
        // stack trace.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Mensagem compreensível para recurso inexistente.
        //
        // O tratamento é registrado para NotFoundHttpException, e não para
        // ModelNotFoundException, porque o Laravel converte a segunda na
        // primeira antes de consultar estes renderizadores — um handler
        // registrado para ModelNotFoundException simplesmente nunca
        // dispara, e a resposta sai com a mensagem padrão "No query results
        // for model [App\\Models\\Customer] 99999", que expõe o namespace
        // interno da aplicação.
        //
        // A exceção anterior distingue os dois casos que chegam aqui: id
        // inexistente numa rota válida, e URL que não casa com rota alguma.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $isMissingRecord = $e->getPrevious() instanceof ModelNotFoundException;

            return response()->json([
                'message' => $isMissingRecord
                    ? 'Registro não encontrado.'
                    : 'Endpoint não encontrado.',
            ], Response::HTTP_NOT_FOUND);
        });

        // Rede de segurança para violação de constraint de unicidade.
        //
        // A validação já barra CPF duplicado, mas há uma janela real entre
        // o `SELECT` da regra `unique` e o `INSERT`: duas requisições
        // simultâneas com o mesmo CPF passam ambas pela validação e uma
        // delas esbarra na constraint. Sem este tratamento, o cliente
        // receberia 500; com ele, recebe o mesmo 422 que receberia na
        // validação, no formato que o formulário já sabe exibir.
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Já existe um cliente cadastrado com este CPF.',
                'errors' => ['cpf' => ['Já existe um cliente cadastrado com este CPF.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
