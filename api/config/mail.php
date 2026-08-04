<?php

return [
    // Não há envio de e-mail nesta primeira versão do sistema. O driver
    // "log" grava qualquer tentativa em storage/logs em vez de enviar de
    // verdade — seguro por padrão até termos um provedor configurado.
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'alertas@tuniku.com'),
        'name' => env('MAIL_FROM_NAME', 'Alertas'),
    ],
];
