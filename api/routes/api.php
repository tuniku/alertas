<?php

use App\Http\Controllers\AlertaAtivoController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AlertaLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\ProjetoController;
use App\Http\Controllers\TipoDisparoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
| O endpoint de eventos é chamado pelos sistemas externos e, por decisão
| de projeto, não exige autenticação nesta etapa (rede interna).
*/
Route::post('/eventos', [EventoController::class, 'disparar']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Rotas autenticadas (Sanctum - token Bearer)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('projetos', ProjetoController::class);
    Route::apiResource('alertas', AlertaController::class);
    Route::apiResource('usuarios', UsuarioController::class)
        ->parameters(['usuarios' => 'usuario']);

    Route::get('/tipos-disparo', [TipoDisparoController::class, 'index']);

    Route::get('/alertas-ativos', [AlertaAtivoController::class, 'index']);
    Route::post('/alertas-ativos/{alertaAtivo}/fechar', [AlertaAtivoController::class, 'fechar']);

    Route::get('/logs', [AlertaLogController::class, 'index']);
});
