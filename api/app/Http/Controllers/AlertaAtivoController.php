<?php

namespace App\Http\Controllers;

use App\Jobs\EncerrarNotificacaoAlerta;
use App\Models\AlertaAtivo;
use App\Notificacoes\NotificadorFactory;
use App\Notificacoes\NotificadorReversivel;
use Illuminate\Http\Request;

class AlertaAtivoController extends Controller
{
    public function index(Request $request)
    {
        return AlertaAtivo::with(['alerta.projeto', 'fechadoPor'])
            ->when(
                $request->query('somente_ativos', '1') === '1',
                fn ($q) => $q->where('ativo', true)
            )
            ->orderByDesc('updated_at')
            ->paginate(50);
    }

    public function fechar(Request $request, AlertaAtivo $alertaAtivo)
    {
        if (! $alertaAtivo->ativo) {
            return response()->json(['mensagem' => 'Este alerta já está fechado.'], 422);
        }

        $alertaAtivo->update([
            'ativo' => false,
            'fechado_por' => $request->user()->id,
            'fechado_em' => now(),
        ]);

        // Só depois de gravar o fechamento: o job consulta os alertas
        // ainda abertos para decidir se apaga a lâmpada, e precisa
        // enxergar este registro já como fechado.
        $this->enfileirarEncerramentos($alertaAtivo);

        return $alertaAtivo->load(['alerta.projeto', 'fechadoPor']);
    }

    /**
     * Aciona a ação de encerramento nos canais que têm uma — hoje, a
     * lâmpada da Tuya. Canais sem nada a desfazer (Discord, Telegram)
     * não implementam NotificadorReversivel e são ignorados aqui, sem
     * precisar de nenhuma condição por nome de driver.
     */
    private function enfileirarEncerramentos(AlertaAtivo $alertaAtivo): void
    {
        $tipos = $alertaAtivo->alerta
            ->tiposDisparo()
            ->where('ativo', true)
            ->get();

        $classes = NotificadorFactory::disponiveis();

        foreach ($tipos as $tipo) {
            // Comparação pela classe registrada, sem instanciar: um
            // driver que tenha saído da factory (renomeado, removido num
            // deploy) apenas não entra na lista, em vez de derrubar o
            // fechamento do alerta com uma exceção.
            $classe = $classes[$tipo->driver] ?? null;

            if ($classe && is_subclass_of($classe, NotificadorReversivel::class)) {
                EncerrarNotificacaoAlerta::dispatch($alertaAtivo->id, $tipo->id);
            }
        }
    }
}
