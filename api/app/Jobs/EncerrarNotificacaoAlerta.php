<?php

namespace App\Jobs;

use App\Models\AlertaAtivo;
use App\Models\NotificacaoLog;
use App\Models\TipoDisparo;
use App\Notificacoes\MensagemAlerta;
use App\Notificacoes\NotificadorFactory;
use App\Notificacoes\NotificadorReversivel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executa a ação de encerramento de um canal quando um alerta ativo é
 * fechado — hoje, apagar a lâmpada da Tuya.
 *
 * Espelha o EnviarNotificacaoAlerta: um job por canal, mesma política de
 * retentativa, mesmo registro em notificacao_logs.
 */
class EncerrarNotificacaoAlerta implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int> segundos entre as tentativas */
    public array $backoff = [10, 60];

    public function __construct(
        public int $alertaAtivoId,
        public int $tipoDisparoId,
    ) {
    }

    public function handle(): void
    {
        $ativo = AlertaAtivo::with('alerta.projeto')->find($this->alertaAtivoId);
        $tipoDisparo = TipoDisparo::find($this->tipoDisparoId);

        if (! $ativo || ! $tipoDisparo || ! $tipoDisparo->ativo) {
            return;
        }

        $notificador = NotificadorFactory::criar($tipoDisparo->driver);

        // Guarda de segurança: o canal pode ter deixado de ser reversível
        // entre o enfileiramento e a execução (edição do tipo de disparo,
        // deploy). Sem isso, seria um erro fatal em vez de um no-op.
        if (! $notificador instanceof NotificadorReversivel) {
            return;
        }

        $mensagem = MensagemAlerta::doAlertaAtivo($ativo);

        try {
            // Se outro alerta ainda ativo usa este mesmo canal, encerrar
            // seria apagar um aviso legítimo. Em vez disso, reenviamos a
            // notificação do mais grave que restou — a lâmpada passa a
            // refletir o pior problema em aberto, em vez de sumir.
            $remanescente = $this->maisGraveAindaAtivo($tipoDisparo);

            if ($remanescente) {
                $notificador->enviar(
                    MensagemAlerta::doAlertaAtivo($remanescente),
                    $tipoDisparo
                );
            } else {
                $notificador->encerrar($mensagem, $tipoDisparo);
            }

            $this->registrar($tipoDisparo, true);
        } catch (Throwable $e) {
            $this->registrar($tipoDisparo, false, $e->getMessage());

            Log::warning('Falha ao encerrar notificação de alerta', [
                'alerta_ativo_id' => $this->alertaAtivoId,
                'tipo_disparo' => $tipoDisparo->nome,
                'driver' => $tipoDisparo->driver,
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Alerta ativo de maior importância que ainda usa este canal, sem
     * contar o que acabou de ser fechado.
     *
     * A expiração é conferida em PHP (via bloqueiaNovoDisparo) e não no
     * SQL porque é a mesma regra usada na deduplicação: um registro
     * ativo mas vencido não conta como aberto. Duplicar essa condição
     * numa cláusula where abriria espaço para as duas divergirem.
     */
    private function maisGraveAindaAtivo(TipoDisparo $tipoDisparo): ?AlertaAtivo
    {
        return AlertaAtivo::with('alerta.projeto')
            ->where('ativo', true)
            ->where('id', '!=', $this->alertaAtivoId)
            ->whereHas(
                'alerta.tiposDisparo',
                fn ($q) => $q->where('tipos_disparo.id', $tipoDisparo->id)
            )
            ->get()
            ->filter->bloqueiaNovoDisparo()
            ->sortByDesc(fn (AlertaAtivo $a) => $a->alerta->importancia)
            ->first();
    }

    private function registrar(TipoDisparo $tipoDisparo, bool $sucesso, ?string $erro = null): void
    {
        NotificacaoLog::create([
            'alerta_ativo_id' => $this->alertaAtivoId,
            'tipo_disparo_id' => $tipoDisparo->id,
            'driver' => $tipoDisparo->driver,
            'sucesso' => $sucesso,
            'erro' => $erro,
        ]);
    }
}
