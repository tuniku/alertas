<?php

namespace App\Jobs;

use App\Models\AlertaAtivo;
use App\Models\Dispositivo;
use App\Services\Fcm\FcmClient;
use App\Services\Fcm\FcmTokenInvalidoException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia a notificação push de um alerta ativo a todos os dispositivos
 * cadastrados.
 *
 * Diferente de EnviarNotificacaoAlerta (um job por canal configurado no
 * alerta, disparado só para quem foi escolhido no cadastro), o push não
 * é um tipo de disparo selecionável: dispara uma vez, automaticamente,
 * para qualquer alerta marcado como "disponível no aplicativo" — e vai
 * para todos os aparelhos, porque qualquer usuário cadastrado pode ver
 * qualquer alerta disponível no app.
 */
class EnviarPushAlerta implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int> segundos entre as tentativas */
    public array $backoff = [10, 60];

    public function __construct(
        public int $alertaAtivoId,
        public ?string $descricao = null,
    ) {
    }

    public function handle(FcmClient $fcm): void
    {
        $ativo = AlertaAtivo::with('alerta.projeto')->find($this->alertaAtivoId);

        // Repete a checagem de disponivel_app aqui (e não só no
        // EventoController) porque, entre o enfileiramento e a execução,
        // o alerta pode ter sido desmarcado — nesse intervalo o push não
        // deveria mais sair.
        if (! $ativo || ! $ativo->alerta?->disponivel_app) {
            return;
        }

        $dispositivos = Dispositivo::all();

        if ($dispositivos->isEmpty()) {
            return;
        }

        $titulo = $ativo->alerta->nome;
        $corpo = $this->descricao ?: ($ativo->alerta->projeto?->nome ?? 'Alertas');

        foreach ($dispositivos as $dispositivo) {
            try {
                $fcm->enviar($dispositivo->token, $titulo, $corpo, [
                    'alerta_ativo_id' => (string) $ativo->id,
                    'tipo' => 'alerta',
                ]);
            } catch (FcmTokenInvalidoException) {
                // Autolimpeza: token de app desinstalado (ou com dados
                // limpos) não deve ficar acumulando tentativas fadadas a
                // falhar para sempre a cada alerta novo.
                $dispositivo->delete();
            } catch (Throwable $e) {
                // Não relança: a falha em UM dispositivo não deve fazer
                // o job inteiro repetir o envio para todos os outros,
                // que já podem ter recebido com sucesso. O preço é que
                // este dispositivo específico não tem retentativa nesta
                // notificação — aceitável para um push, que por natureza
                // já não é garantido.
                Log::warning('Falha ao enviar push do alerta', [
                    'alerta_ativo_id' => $this->alertaAtivoId,
                    'dispositivo_id' => $dispositivo->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }
    }
}
