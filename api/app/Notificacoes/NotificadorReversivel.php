<?php

namespace App\Notificacoes;

use App\Models\TipoDisparo;

/**
 * Contrato adicional para canais que têm uma ação de *encerramento*,
 * executada quando o alerta ativo é fechado.
 *
 * Existe como interface separada, e não como um método a mais em
 * Notificador, porque nem todo canal tem o que "desfazer": uma mensagem
 * já postada no Discord não some. Obrigar todos os drivers a declarar um
 * método vazio só para satisfazer o contrato seria ruído — aqui, quem
 * tem estado a restaurar (uma lâmpada acesa, um relé ligado) adere; os
 * demais simplesmente não implementam, e o sistema não os aciona.
 *
 * Se no futuro o Discord passar a postar "alerta fechado", basta ele
 * implementar esta interface: nenhum outro arquivo muda.
 */
interface NotificadorReversivel
{
    /**
     * Desfaz/encerra o efeito da notificação.
     *
     * Recebe a mensagem do alerta que está sendo fechado — útil para
     * canais que queiram informar o que foi encerrado. Deve lançar
     * exceção em caso de falha, como o enviar().
     */
    public function encerrar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void;
}
