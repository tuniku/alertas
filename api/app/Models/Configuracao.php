<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuração global no formato chave/valor.
 *
 * Diferente de tipos_disparo (que é cadastro do usuário, com vários
 * registros), aqui cada chave existe no máximo uma vez e representa um
 * ajuste do sistema.
 */
class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $primaryKey = 'chave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['chave', 'valor'];

    /** Chaves usadas pelo módulo de leads. */
    public const LEADS_DISCORD_WEBHOOK = 'leads_discord_webhook';

    public const LEADS_TOKEN = 'leads_token';

    /**
     * JSON da conta de serviço do Firebase (Contas de serviço → Gerar
     * nova chave privada), usado pelo servidor para enviar push via FCM.
     */
    public const PUSH_FCM_SERVICE_ACCOUNT = 'push_fcm_service_account';

    public static function obter(string $chave, ?string $padrao = null): ?string
    {
        return static::find($chave)?->valor ?? $padrao;
    }

    public static function definir(string $chave, ?string $valor): void
    {
        static::updateOrCreate(['chave' => $chave], ['valor' => $valor]);
    }
}
