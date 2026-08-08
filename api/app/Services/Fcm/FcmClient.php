<?php

namespace App\Services\Fcm;

use App\Models\Configuracao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente do FCM HTTP v1, escrito à mão porque não existe SDK Admin
 * oficial do Firebase para PHP. Reproduz o que o SDK faria por baixo:
 * assina um JWT com a chave privada da conta de serviço, troca por um
 * access token OAuth2 junto ao Google, e então chama o endpoint de
 * envio — o mesmo desenho de "assinar e trocar por token" já usado no
 * TuyaNotificador, só que com RS256 em vez de HMAC.
 *
 * Configuração esperada em `configuracoes` (chave
 * Configuracao::PUSH_FCM_SERVICE_ACCOUNT): o conteúdo integral do JSON
 * baixado em Firebase Console → Configurações do projeto → Contas de
 * serviço → Gerar nova chave privada. Não é o mesmo arquivo que o app
 * usa (google-services.json) — aquele serve só para RECEBER; este serve
 * para o servidor ENVIAR.
 */
class FcmClient
{
    private const ESCOPO = 'https://www.googleapis.com/auth/firebase.messaging';

    private const URL_TOKEN = 'https://oauth2.googleapis.com/token';

    /**
     * Envia para um único token de dispositivo. O FCM v1 não tem envio
     * em lote num único request — isso existia só na API legada — então,
     * para o punhado de dispositivos deste sistema, chamar uma vez por
     * token é simples e suficiente.
     *
     * @param  array<string, string>  $dados  vai no payload "data" da mensagem
     *
     * @throws FcmTokenInvalidoException quando o FCM diz que o token não existe mais
     */
    public function enviar(string $token, string $titulo, string $corpo, array $dados = []): void
    {
        $config = $this->config();

        $resposta = Http::withToken($this->accessToken($config))
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$config['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $titulo,
                        'body' => $corpo,
                    ],
                    'data' => array_map('strval', $dados),
                    // Prioridade alta: um alerta precisa acordar o
                    // aparelho, não esperar a próxima janela de economia
                    // de bateria do Android para entregar a mensagem.
                    'android' => [
                        'priority' => 'high',
                    ],
                ],
            ]);

        if ($resposta->successful()) {
            return;
        }

        $status = $resposta->json('error.status');

        // UNREGISTERED (e, em alguns casos, NOT_FOUND) significa app
        // desinstalado ou dados do app limpos — sinalizamos para o
        // chamador remover o registro, em vez de tratar como falha
        // passageira que vale repetir.
        if (in_array($status, ['UNREGISTERED', 'NOT_FOUND'], true)) {
            throw new FcmTokenInvalidoException($token);
        }

        throw new RuntimeException(
            "FCM recusou o envio (HTTP {$resposta->status()}): ".$resposta->body()
        );
    }

    /**
     * O access token do Google vale por volta de 1h. Pedir um novo a
     * cada push seria uma chamada extra por notificação; o cache
     * economiza isso, com a mesma folga de 60s antes do vencimento usada
     * no cache do token da Tuya.
     */
    private function accessToken(array $config): string
    {
        $chave = 'fcm:access_token:'.md5($config['client_email']);

        if ($token = Cache::get($chave)) {
            return $token;
        }

        $jwt = $this->assinarJwt($config);

        $resposta = Http::asForm()->timeout(10)->post(self::URL_TOKEN, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($resposta->failed() || ! $resposta->json('access_token')) {
            throw new RuntimeException(
                'Não foi possível obter o access token do Google: '.$resposta->body()
            );
        }

        $token = $resposta->json('access_token');
        $segundos = (int) ($resposta->json('expires_in') ?? 3600);

        Cache::put($chave, $token, max(60, $segundos - 60));

        return $token;
    }

    /**
     * JWT assinado com a chave privada da conta de serviço (RS256), no
     * formato que o Google exige para o fluxo "de servidor para
     * servidor" (grant_type jwt-bearer). Feito à mão com openssl_sign
     * (extensão nativa do PHP) em vez de uma lib de JWT, pelo mesmo
     * motivo do HMAC manual da Tuya: é a única coisa que este cliente
     * precisa fazer com JWT, e trazer uma dependência para uma assinatura
     * custaria mais para entender do que economiza.
     */
    private function assinarJwt(array $config): string
    {
        $agora = time();

        $cabecalho = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

        $corpo = $this->base64Url(json_encode([
            'iss' => $config['client_email'],
            'scope' => self::ESCOPO,
            'aud' => self::URL_TOKEN,
            'iat' => $agora,
            'exp' => $agora + 3600,
        ]));

        $assinatura = '';
        $ok = openssl_sign(
            "{$cabecalho}.{$corpo}",
            $assinatura,
            $config['private_key'],
            'sha256WithRSAEncryption'
        );

        if (! $ok) {
            throw new RuntimeException('Falha ao assinar o JWT com a chave da conta de serviço.');
        }

        return "{$cabecalho}.{$corpo}.".$this->base64Url($assinatura);
    }

    private function base64Url(string $dado): string
    {
        return rtrim(strtr(base64_encode($dado), '+/', '-_'), '=');
    }

    /** @return array{project_id: string, client_email: string, private_key: string} */
    private function config(): array
    {
        $bruto = Configuracao::obter(Configuracao::PUSH_FCM_SERVICE_ACCOUNT);

        if (! $bruto) {
            throw new RuntimeException('Nenhuma credencial do Firebase configurada.');
        }

        $json = json_decode($bruto, true);

        foreach (['project_id', 'client_email', 'private_key'] as $chave) {
            if (empty($json[$chave] ?? null)) {
                throw new RuntimeException("Credencial do Firebase inválida: falta '{$chave}'.");
            }
        }

        return $json;
    }
}
