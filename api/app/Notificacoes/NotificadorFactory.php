<?php

namespace App\Notificacoes;

use App\Notificacoes\Drivers\DiscordNotificador;
use InvalidArgumentException;

/**
 * Ponto único de registro dos canais disponíveis. Para adicionar
 * e-mail, Telegram ou Tuya: criar a classe do driver implementando
 * Notificador e acrescentar uma linha no array abaixo.
 */
class NotificadorFactory
{
    private const DRIVERS = [
        'discord' => DiscordNotificador::class,
    ];

    public static function criar(string $driver): Notificador
    {
        $classe = self::DRIVERS[$driver] ?? null;

        if (! $classe) {
            throw new InvalidArgumentException("Driver de disparo desconhecido: {$driver}");
        }

        return new $classe;
    }

    /** @return array<string, class-string<Notificador>> */
    public static function disponiveis(): array
    {
        return self::DRIVERS;
    }

    /** Lista para o frontend montar o seletor e o formulário dinâmico. */
    public static function paraInterface(): array
    {
        return collect(self::DRIVERS)
            ->map(fn (string $classe, string $driver) => [
                'driver' => $driver,
                'rotulo' => $classe::rotulo(),
                'campos' => array_map(
                    fn (string $chave) => str_replace('configuracao.', '', $chave),
                    array_keys($classe::regrasDeConfiguracao())
                ),
            ])
            ->values()
            ->all();
    }

    public static function regrasDe(string $driver): array
    {
        $classe = self::DRIVERS[$driver] ?? null;

        return $classe ? $classe::regrasDeConfiguracao() : [];
    }
}
