<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\Dispositivo;
use App\Services\Fcm\FcmClient;
use Illuminate\Http\Request;
use Throwable;

/**
 * Configuração do push: a credencial da conta de serviço do Firebase,
 * usada pelo SERVIDOR para se autenticar no FCM e enviar mensagens.
 *
 * Não confundir com o google-services.json embutido no app — aquele
 * identifica o aplicativo para RECEBER mensagens; esta credencial é o
 * que autoriza o backend a ENVIAR.
 */
class ConfiguracaoPushController extends Controller
{
    public function show()
    {
        $bruto = Configuracao::obter(Configuracao::PUSH_FCM_SERVICE_ACCOUNT);
        $json = $bruto ? json_decode($bruto, true) : null;

        return response()->json([
            // A chave privada nunca volta ao navegador — só o suficiente
            // para confirmar que algo está configurado e identificar o
            // projeto, para conferência visual.
            'configurado' => (bool) $bruto,
            'project_id' => $json['project_id'] ?? null,
            'client_email' => $json['client_email'] ?? null,
        ]);
    }

    public function update(Request $request)
    {
        $dados = $request->validate([
            'service_account_json' => ['nullable', 'string'],
        ]);

        $bruto = $dados['service_account_json'] ?? null;

        if ($bruto) {
            $json = json_decode($bruto, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['mensagem' => 'JSON inválido.'], 422);
            }

            foreach (['project_id', 'client_email', 'private_key'] as $chave) {
                if (empty($json[$chave] ?? null)) {
                    return response()->json(['mensagem' => "Falta o campo '{$chave}' no JSON."], 422);
                }
            }
        }

        Configuracao::definir(Configuracao::PUSH_FCM_SERVICE_ACCOUNT, $bruto);

        return $this->show();
    }

    /**
     * Envia um push de teste a todos os dispositivos do usuário
     * autenticado — evita precisar disparar um alerta de verdade só
     * para confirmar que a credencial está correta.
     */
    public function testar(Request $request)
    {
        $dispositivos = Dispositivo::where('user_id', $request->user()->id)->get();

        if ($dispositivos->isEmpty()) {
            return response()->json([
                'mensagem' => 'Nenhum dispositivo seu está cadastrado. Entre no aplicativo com este usuário primeiro.',
            ], 422);
        }

        $fcm = new FcmClient;
        $erro = null;

        foreach ($dispositivos as $dispositivo) {
            try {
                $fcm->enviar(
                    $dispositivo->token,
                    'Teste de push',
                    'Se você recebeu isto, a configuração está correta.'
                );
            } catch (Throwable $e) {
                $erro ??= $e->getMessage();
            }
        }

        if ($erro) {
            return response()->json(['mensagem' => 'Falha ao enviar: '.$erro], 422);
        }

        return response()->json(['mensagem' => 'Push de teste enviado.']);
    }
}
