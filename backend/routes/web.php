<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas web
|--------------------------------------------------------------------------
|
| Este backend serve apenas a API; a interface é um projeto Vue separado,
| em `frontend/`. Não há views Blade nem assets compilados aqui, e por isso
| a raiz responde com uma orientação em JSON em vez de uma página.
|
| A rota existe para que `GET /` seja informativa em vez de 404 — quem abre
| a URL do backend no navegador descobre onde está a API e onde está a
| interface.
|
*/

Route::get('/', fn () => response()->json([
    'application' => config('app.name'),
    'description' => 'API de gerenciamento de clientes — Desafio Técnico Nogle',
    'api' => url('/api/customers'),
    'health' => url('/up'),
]));
