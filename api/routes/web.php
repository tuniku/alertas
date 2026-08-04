<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'aplicacao' => 'Alertas API',
    'versao' => '1.0.0',
]));
