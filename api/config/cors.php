<?php

return [
    // Como frontend e API respondem no mesmo domínio (alertas.tuniku.com),
    // o navegador nem chega a fazer requisição cross-origin — este
    // arquivo existe só porque o middleware HandleCors (global, embutido
    // no framework) espera essas chaves configuradas.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
