<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sem isto, uma requisição não autenticada faz o middleware
        // Authenticate tentar redirecionar para a rota chamada "login"
        // — que não existe aqui, porque este projeto é API pura, sem
        // telas no backend. O resultado seria um 500 ("Route [login] not
        // defined") no lugar do 401 esperado. Retornar null desliga o
        // redirect e faz o Laravel responder 401 em JSON.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sistemas externos nem sempre enviam "Accept: application/json";
        // força resposta JSON (e não redirect) para qualquer rota da API.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
