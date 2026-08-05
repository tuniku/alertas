<?php

namespace App\Notificacoes;

use App\Models\AlertaAtivo;

/**
 * Dados do alerta já prontos para notificação, num formato neutro que
 * qualquer driver consegue consumir. Existe para que os drivers não
 * precisem conhecer o Eloquent nem a estrutura das tabelas: o driver
 * da Tuya, por exemplo, só vai olhar a importância para decidir a cor
 * da lâmpada, sem saber o que é um AlertaAtivo.
 */
class MensagemAlerta
{
    public function __construct(
        public readonly string $projeto,
        public readonly string $alerta,
        public readonly string $codigo,
        public readonly int $importancia,
        public readonly ?string $descricao,
        public readonly ?string $eventoEm,
        public readonly string $recebidoEm,
    ) {
    }

    public static function doAlertaAtivo(AlertaAtivo $ativo, ?string $descricao = null, ?string $eventoEm = null): self
    {
        $alerta = $ativo->alerta;

        return new self(
            projeto: $alerta->projeto?->nome ?? '—',
            alerta: $alerta->nome,
            codigo: $alerta->codigo,
            importancia: $alerta->importancia,
            descricao: $descricao,
            eventoEm: $eventoEm,
            recebidoEm: $ativo->created_at->format('d/m/Y H:i:s'),
        );
    }

    /** Rótulo textual da severidade, útil para e-mail e Telegram. */
    public function nivel(): string
    {
        return match (true) {
            $this->importancia >= 8 => 'Crítico',
            $this->importancia >= 4 => 'Atenção',
            default => 'Informativo',
        };
    }
}
