<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas da API
|--------------------------------------------------------------------------
|
| Autenticação por sessão com cookie (Sanctum em modo SPA). O middleware
| `auth:sanctum` reconhece a sessão porque EnsureFrontendRequestsAreStateful,
| registrado em bootstrap/app.php, torna a requisição stateful quando ela vem
| de um domínio listado em SANCTUM_STATEFUL_DOMAINS.
|
*/

Route::post('login', [AuthController::class, 'login'])
    ->middleware('guest')
    ->name('login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');

    // `destroy` não é registrado: o requisito pede inativação, não exclusão,
    // e expor DELETE significaria oferecer uma operação que a aplicação não
    // deve ter.
    Route::apiResource('customers', CustomerController::class)
        ->except(['destroy']);

    Route::patch('customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])
        ->name('customers.deactivate');

    Route::patch('customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])
        ->name('customers.reactivate');
});
