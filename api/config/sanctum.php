<?php

use Laravel\Sanctum\Sanctum;

return [
    // Domínios que autenticam via cookie de sessão (modo SPA do Sanctum).
    // Não é o nosso caso: o frontend usa token Bearer puro (ver
    // web/src/api.js), então esta lista não é consultada na prática,
    // mas precisa existir para o pacote inicializar sem erro.
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    // Tokens não expiram automaticamente nesta primeira versão — cada
    // usuário permanece logado até fazer logout manualmente.
    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
