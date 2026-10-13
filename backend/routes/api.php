<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CustomerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas da API
|--------------------------------------------------------------------------
|
| Etapa 1: as rotas estão abertas. A autenticação por sessão (Sanctum em
| modo SPA) e a autorização por Policy entram na Etapa 3, conforme o
| faseamento acordado — as rotas passarão para dentro de um grupo
| `auth:sanctum` e os métodos de escrita ganharão verificação de perfil.
|
*/

Route::apiResource('customers', CustomerController::class)
    // `destroy` não existe: o requisito pede inativação, não exclusão, e
    // registrar a rota significaria oferecer uma operação que a aplicação
    // não deve ter.
    ->except(['destroy']);

Route::patch('customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])
    ->name('customers.deactivate');

Route::patch('customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])
    ->name('customers.reactivate');
