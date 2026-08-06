<?php

use App\Http\Controllers\AlertaAtivoController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AlertaLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfiguracaoLeadController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadWebhookController;
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

// Webhook de saída do FunnelsFlow. Protegido por token compartilhado
// (header X-Webhook-Token ou ?token=), configurado na tela de leads.
Route::post('/leads/webhook', [LeadWebhookController::class, 'receber']);

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

    // Metadados dos drivers disponíveis — precisa vir antes do
    // apiResource, senão "drivers" seria interpretado como um {id}.
    Route::get('/tipos-disparo/drivers', [TipoDisparoController::class, 'drivers']);
    Route::post('/tipos-disparo/{tipoDisparo}/testar', [TipoDisparoController::class, 'testar']);
    Route::apiResource('tipos-disparo', TipoDisparoController::class)
        ->parameters(['tipos-disparo' => 'tipoDisparo']);

    Route::get('/alertas-ativos', [AlertaAtivoController::class, 'index']);
    Route::post('/alertas-ativos/{alertaAtivo}/fechar', [AlertaAtivoController::class, 'fechar']);

    Route::get('/logs', [AlertaLogController::class, 'index']);

    // Leads (FunnelsFlow). As rotas específicas vêm antes de /leads/{lead}
    // para não serem capturadas como se "origens" fosse um id.
    Route::get('/leads/origens', [LeadController::class, 'origens']);
    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/{lead}', [LeadController::class, 'show']);

    Route::get('/configuracoes/leads', [ConfiguracaoLeadController::class, 'show']);
    Route::put('/configuracoes/leads', [ConfiguracaoLeadController::class, 'update']);
    Route::post('/configuracoes/leads/token', [ConfiguracaoLeadController::class, 'gerarToken']);
    Route::post('/configuracoes/leads/testar', [ConfiguracaoLeadController::class, 'testar']);
});
