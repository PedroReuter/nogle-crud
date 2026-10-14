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

    // `destroy` está registrado, mas NÃO é a inativação.
    //
    // O requisito é claro: inativar não apaga, e continua não apagando — essa
    // é a rota `deactivate`. O DELETE existe para outro propósito, a
    // eliminação definitiva prevista na LGPD, quando o titular pede que seus
    // dados sejam apagados. A Policy exige que o cliente já esteja inativo,
    // de modo que eliminar é sempre um segundo passo deliberado.
    Route::apiResource('customers', CustomerController::class);

    // Histórico de alterações, para responsabilização.
    Route::get('customers/{customer}/audits', [CustomerController::class, 'audits'])
        ->name('customers.audits');

    Route::patch('customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])
        ->name('customers.deactivate');

    Route::patch('customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])
        ->name('customers.reactivate');
});
