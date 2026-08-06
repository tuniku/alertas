<?php

namespace App\Notificacoes\Drivers;

use App\Models\TipoDisparo;
use App\Notificacoes\MensagemAlerta;
use App\Notificacoes\Notificador;
use App\Notificacoes\NotificadorReversivel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Acende uma lâmpada inteligente da Tuya na cor correspondente à
 * severidade do alerta.
 *
 * Este driver é o primeiro que não "envia mensagem" — aciona um
 * dispositivo. Ainda assim cabe na mesma interface: o que muda é só o
 * que acontece dentro de enviar().
 *
 * Configuração esperada em tipos_disparo.configuracao:
 *   {
 *     "regiao": "us",                       // data center da conta Tuya
 *     "access_id": "vww4v98rdk8pructtp9e",  // "Client ID" no painel
 *     "access_secret": "...",               // "Client Secret" (segredo)
 *     "device_id": "ebf20a7fd116870225ercg"
 *   }
 *
 * A lâmpada é apagada quando o alerta ativo é fechado na tela (por isso
 * o driver também implementa NotificadorReversivel). Ela NÃO apaga
 * sozinha por tempo: enquanto o alerta estiver aberto, o aviso continua
 * visível.
 */
class TuyaNotificador implements Notificador, NotificadorReversivel
{
    /**
     * A Tuya isola as contas por data center: um access_id criado na
     * região errada responde "sign invalid" ou "no permissions", o que
     * confunde com erro de credencial.
     */
    private const ENDPOINTS = [
        'us' => 'https://openapi.tuyaus.com',
        'eu' => 'https://openapi.tuyaeu.com',
        'cn' => 'https://openapi.tuyacn.com',
        'in' => 'https://openapi.tuyain.com',
    ];

    /** Códigos de erro da Tuya que significam "token inválido/expirado". */
    private const CODIGOS_DE_TOKEN = [1010, 1011, 1012];

    public static function rotulo(): string
    {
        return 'Tuya (lâmpada)';
    }

    public static function regrasDeConfiguracao(): array
    {
        return [
            'configuracao.regiao' => ['required', 'string', 'in:us,eu,cn,in'],
            'configuracao.access_id' => ['required', 'string'],
            'configuracao.access_secret' => ['required', 'string'],
            'configuracao.device_id' => ['required', 'string'],
        ];
    }

    public function enviar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void
    {
        [$h, $s, $v] = $this->cor($mensagem->importancia);

        // Os três comandos vão juntos e são aplicados em ordem. O
        // work_mode = "colour" é obrigatório antes da cor: se a lâmpada
        // estiver em modo branco, ela ignora colour_data_v2 e continua
        // branca — falha silenciosa, sem erro na resposta da API.
        $this->comandar($tipoDisparo, [
            ['code' => 'switch_led', 'value' => true],
            ['code' => 'work_mode', 'value' => 'colour'],
            ['code' => 'colour_data_v2', 'value' => ['h' => $h, 's' => $s, 'v' => $v]],
        ]);
    }

    /**
     * Apaga a lâmpada quando o alerta ativo é fechado.
     *
     * Só desliga: não restaura cor nem modo anterior. Guardar e repor o
     * estado que a lâmpada tinha antes exigiria consultar o dispositivo
     * a cada alerta e persistir esse estado — complexidade que não se
     * justifica para uma lâmpada de aviso.
     */
    public function encerrar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void
    {
        $this->comandar($tipoDisparo, [
            ['code' => 'switch_led', 'value' => false],
        ]);
    }

    /**
     * Envia um conjunto de comandos ao dispositivo, cuidando de token,
     * assinatura e do retry quando o token expira. É o ponto único por
     * onde passam tanto o acender quanto o apagar.
     *
     * @param  array<int, array<string, mixed>>  $comandos
     */
    private function comandar(TipoDisparo $tipoDisparo, array $comandos): void
    {
        $config = $this->config($tipoDisparo);

        $corpo = json_encode(
            ['commands' => $comandos],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $caminho = "/v1.0/devices/{$config['device_id']}/commands";

        $resposta = $this->requisicao('POST', $caminho, $corpo, $config, $this->token($config));

        // Token pode ter expirado entre o cache e o envio. Nesse caso
        // vale uma segunda tentativa com token novo — é diferente de um
        // erro real, e refazer aqui evita ocupar a fila com retentativa.
        if (! $this->deuCerto($resposta) && in_array($resposta['code'] ?? 0, self::CODIGOS_DE_TOKEN, true)) {
            $resposta = $this->requisicao('POST', $caminho, $corpo, $config, $this->token($config, forcarNovo: true));
        }

        if (! $this->deuCerto($resposta)) {
            throw new RuntimeException(sprintf(
                'Tuya recusou o comando (code %s): %s',
                $resposta['code'] ?? '?',
                $resposta['msg'] ?? 'sem detalhe'
            ));
        }
    }

    /**
     * Cor por severidade, em HSV. Os códigos "_v2" da Tuya usam matiz
     * 0–360 e saturação/brilho em 0–1000 (e não 0–255, que é a escala
     * dos códigos antigos como colour_data e bright_value) — usar a
     * escala errada acende a lâmpada quase apagada, sem dar erro.
     *
     * @return array{int, int, int} [matiz, saturação, brilho]
     */
    private function cor(int $importancia): array
    {
        return match (true) {
            $importancia >= 8 => [0, 1000, 1000],    // vermelho
            $importancia >= 4 => [40, 1000, 1000],   // âmbar
            default => [120, 1000, 1000],            // verde
        };
    }

    /**
     * O token da Tuya vale 2 horas. Pedir um novo a cada alerta seria
     * uma chamada extra desnecessária (e a Tuya limita requisições), por
     * isso ele fica em cache, com uma folga de 60s antes do vencimento.
     */
    private function token(array $config, bool $forcarNovo = false): string
    {
        $chave = 'tuya:token:'.$config['access_id'];

        if (! $forcarNovo && ($token = Cache::get($chave))) {
            return $token;
        }

        // Na chamada de token o access_token ainda não existe, e é
        // justamente essa a diferença entre a assinatura "de token" e a
        // "de negócio" no script do Postman.
        $resposta = $this->requisicao('GET', '/v1.0/token?grant_type=1', '', $config, null);

        if (! $this->deuCerto($resposta) || empty($resposta['result']['access_token'])) {
            throw new RuntimeException(sprintf(
                'Tuya não devolveu token (code %s): %s',
                $resposta['code'] ?? '?',
                $resposta['msg'] ?? 'sem detalhe'
            ));
        }

        $token = $resposta['result']['access_token'];
        $segundos = (int) ($resposta['result']['expire_time'] ?? 7200);

        Cache::put($chave, $token, max(60, $segundos - 60));

        return $token;
    }

    /**
     * Monta e executa uma requisição assinada. Reproduz exatamente o
     * algoritmo do pre-request script da coleção oficial da Tuya:
     *
     *   signStr = MÉTODO \n SHA256(corpo) \n headersStr \n caminho
     *   base    = access_id [+ access_token] + t + nonce + signStr
     *   sign    = HMAC-SHA256(base, access_secret) em maiúsculas
     *
     * headersStr fica vazio porque não usamos o header opcional
     * "Signature-Headers" — daí os dois \n seguidos.
     */
    private function requisicao(string $metodo, string $caminho, string $corpo, array $config, ?string $token): array
    {
        // A Tuya espera milissegundos, não segundos. Um timestamp em
        // segundos passa na assinatura mas é recusado por "sign invalid",
        // porque o servidor considera a requisição fora da janela válida.
        $t = (string) (int) round(microtime(true) * 1000);
        $nonce = '';

        $signStr = $metodo."\n".hash('sha256', $corpo)."\n\n".$caminho;
        $base = $config['access_id'].($token ?? '').$t.$nonce.$signStr;
        $sign = strtoupper(hash_hmac('sha256', $base, $config['access_secret']));

        $headers = [
            'client_id' => $config['access_id'],
            'sign' => $sign,
            't' => $t,
            'sign_method' => 'HMAC-SHA256',
            'nonce' => $nonce,
        ];

        if ($token !== null) {
            $headers['access_token'] = $token;
        }

        $url = self::ENDPOINTS[$config['regiao']].$caminho;
        $requisicao = Http::timeout(10)->withHeaders($headers);

        // O corpo precisa ser enviado byte a byte igual ao que foi
        // usado no SHA256 acima. Por isso withBody() com a string já
        // pronta, em vez de deixar o cliente serializar um array.
        $resposta = $metodo === 'GET'
            ? $requisicao->get($url)
            : $requisicao->withBody($corpo, 'application/json')->post($url);

        if ($resposta->failed()) {
            throw new RuntimeException(
                "Tuya respondeu HTTP {$resposta->status()}: ".$resposta->body()
            );
        }

        return $resposta->json() ?? [];
    }

    /** A Tuya responde HTTP 200 mesmo quando recusa; o que vale é "success". */
    private function deuCerto(array $resposta): bool
    {
        return ($resposta['success'] ?? false) === true;
    }

    private function config(TipoDisparo $tipoDisparo): array
    {
        $config = $tipoDisparo->configuracao ?? [];

        foreach (['regiao', 'access_id', 'access_secret', 'device_id'] as $chave) {
            if (empty($config[$chave])) {
                throw new RuntimeException(
                    "Tipo de disparo '{$tipoDisparo->nome}' está sem '{$chave}' configurado."
                );
            }
        }

        if (! isset(self::ENDPOINTS[$config['regiao']])) {
            throw new RuntimeException(
                "Região Tuya inválida: '{$config['regiao']}'. Use us, eu, cn ou in."
            );
        }

        return $config;
    }
}
