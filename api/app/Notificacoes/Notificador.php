<?php

namespace App\Notificacoes;

use App\Models\TipoDisparo;

/**
 * Contrato de todo canal de notificação. Adicionar e-mail, Telegram ou
 * Tuya no futuro significa criar uma classe nova que implemente esta
 * interface e registrá-la na NotificadorFactory — nada mais no sistema
 * precisa mudar.
 */
interface Notificador
{
    /** Entrega a mensagem. Deve lançar exceção em caso de falha (a fila cuida da retentativa). */
    public function enviar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void;

    /**
     * Regras de validação da configuração deste driver, usadas no
     * cadastro do tipo de disparo. Chaves relativas a "configuracao".
     */
    public static function regrasDeConfiguracao(): array;

    /** Rótulo exibido na interface. */
    public static function rotulo(): string;
}
