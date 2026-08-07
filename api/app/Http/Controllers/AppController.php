<?php

namespace App\Http\Controllers;

use App\Models\AlertaAtivo;
use App\Models\AlertaLog;
use Illuminate\Http\Request;

/**
 * Endpoints consumidos pelo aplicativo Android.
 *
 * Existe separado dos controllers do painel web por dois motivos:
 *
 * 1. **Filtro obrigatório.** Toda consulta aqui restringe aos alertas
 *    marcados como `disponivel_app`. Se fosse um parâmetro opcional nos
 *    endpoints existentes, bastaria o app esquecer de enviá-lo (ou
 *    alguém chamar a API na mão) para vazar alertas que não deveriam
 *    aparecer no celular.
 *
 * 2. **Payload enxuto.** O app roda em rede móvel e mostra pouca coisa
 *    na tela; devolver o objeto Eloquent inteiro com relações
 *    aninhadas seria desperdício de banda e bateria.
 *
 * A autenticação é a mesma do painel (token Sanctum via /api/login):
 * qualquer usuário cadastrado entra no app.
 */
class AppController extends Controller
{
    public function ativos(Request $request)
    {
        $pagina = AlertaAtivo::query()
            ->with('alerta.projeto')
            ->where('ativo', true)
            ->whereHas('alerta', fn ($q) => $q->where('disponivel_app', true))
            ->orderByDesc('updated_at')
            ->paginate(50);

        $pagina->getCollection()->transform(fn (AlertaAtivo $a) => $this->formatarAtivo($a));

        return $pagina;
    }

    public function logs(Request $request)
    {
        $pagina = AlertaLog::query()
            ->with('alerta.projeto')
            ->whereHas('alerta', fn ($q) => $q->where('disponivel_app', true))
            ->orderByDesc('recebido_em')
            ->paginate(50);

        $pagina->getCollection()->transform(fn (AlertaLog $log) => [
            'id' => $log->id,
            'projeto' => $log->alerta?->projeto?->nome,
            'alerta' => $log->alerta?->nome,
            'codigo' => $log->alerta?->codigo,
            'importancia' => $log->alerta?->importancia,
            'descricao' => $log->descricao,
            'evento_em' => $log->evento_em?->toIso8601String(),
            'recebido_em' => $log->recebido_em?->toIso8601String(),
        ]);

        return $pagina;
    }

    /**
     * Fecha um alerta ativo a partir do app.
     *
     * O fechamento é global — encerra para todos os usuários, no app e
     * no painel — e reaproveita exatamente o mesmo caminho do botão
     * "Fechar" da web, inclusive o desligamento da lâmpada.
     */
    public function fechar(Request $request, AlertaAtivo $alertaAtivo)
    {
        // Um alerta não marcado para o app não pode ser fechado por ele,
        // mesmo que alguém descubra o id: o app só enxerga (e mexe) no
        // que está marcado como disponível.
        if (! $alertaAtivo->alerta?->disponivel_app) {
            return response()->json(['mensagem' => 'Alerta não disponível no aplicativo.'], 404);
        }

        return app(AlertaAtivoController::class)->fechar($request, $alertaAtivo);
    }

    /** @return array<string, mixed> */
    private function formatarAtivo(AlertaAtivo $ativo): array
    {
        return [
            'id' => $ativo->id,
            'projeto' => $ativo->alerta?->projeto?->nome,
            'alerta' => $ativo->alerta?->nome,
            'codigo' => $ativo->alerta?->codigo,
            'importancia' => $ativo->alerta?->importancia,
            'nivel' => $this->nivel($ativo->alerta?->importancia ?? 0),
            'criado_em' => $ativo->created_at?->toIso8601String(),
            'atualizado_em' => $ativo->updated_at?->toIso8601String(),
            'expira_em' => $ativo->expira_em?->toIso8601String(),
        ];
    }

    /** Mesma escala usada nos canais de notificação (MensagemAlerta::nivel). */
    private function nivel(int $importancia): string
    {
        return match (true) {
            $importancia >= 8 => 'Crítico',
            $importancia >= 4 => 'Atenção',
            default => 'Informativo',
        };
    }
}
