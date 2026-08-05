<?php

namespace App\Jobs;

use App\Models\AlertaAtivo;
use App\Models\NotificacaoLog;
use App\Models\TipoDisparo;
use App\Notificacoes\MensagemAlerta;
use App\Notificacoes\NotificadorFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entrega a notificação de um alerta ativo em um canal específico.
 *
 * É um job por canal (e não um job que percorre todos): se o Discord
 * estiver fora do ar, a retentativa atinge só ele, sem reenviar o que
 * já foi entregue nos outros canais.
 */
class EnviarNotificacaoAlerta implements ShouldQueue
{
    use Queueable;

    /** 3 tentativas com espera crescente — cobre indisponibilidade breve do destino. */
    public int $tries = 3;

    /** @var array<int> segundos entre as tentativas */
    public array $backoff = [10, 60];

    public function __construct(
        public int $alertaAtivoId,
        public int $tipoDisparoId,
        public ?string $descricao = null,
        public ?string $eventoEm = null,
    ) {
    }

    public function handle(): void
    {
        $ativo = AlertaAtivo::with('alerta.projeto')->find($this->alertaAtivoId);
        $tipoDisparo = TipoDisparo::find($this->tipoDisparoId);

        // Registros podem ter sido removidos entre o enfileiramento e a
        // execução; nesse caso não há o que notificar.
        if (! $ativo || ! $tipoDisparo) {
            return;
        }

        if (! $tipoDisparo->ativo) {
            return;
        }

        $mensagem = MensagemAlerta::doAlertaAtivo($ativo, $this->descricao, $this->eventoEm);

        try {
            NotificadorFactory::criar($tipoDisparo->driver)->enviar($mensagem, $tipoDisparo);

            $this->registrar($tipoDisparo, true);
        } catch (Throwable $e) {
            // Grava a falha desta tentativa e relança: o Laravel cuida do
            // backoff e, esgotadas as tentativas, move para failed_jobs.
            $this->registrar($tipoDisparo, false, $e->getMessage());

            Log::warning('Falha ao notificar alerta', [
                'alerta_ativo_id' => $this->alertaAtivoId,
                'tipo_disparo' => $tipoDisparo->nome,
                'driver' => $tipoDisparo->driver,
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }
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
