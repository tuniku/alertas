<?php

namespace App\Services\Fcm;

use RuntimeException;

/**
 * O FCM respondeu dizendo que não reconhece mais este token — o app foi
 * desinstalado, os dados do app foram limpos, ou o token expirou. Quem
 * capturar esta exceção deve apagar o Dispositivo correspondente, em vez
 * de tratar como uma falha passageira que vale repetir.
 */
class FcmTokenInvalidoException extends RuntimeException
{
    public function __construct(public readonly string $token)
    {
        parent::__construct("Token FCM não é mais válido: {$token}");
    }
}
