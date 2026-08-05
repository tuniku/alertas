<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transforma tipos_disparo de tabela de referência vazia em "canais de
 * notificação configurados": cada registro é um destino concreto
 * (ex.: "Discord #alertas-carbel"), com o driver que sabe entregar a
 * mensagem e a configuração específica daquele driver em JSON.
 *
 * Guardar a configuração em JSON evita uma coluna nova a cada
 * integração futura (e-mail, Telegram, Tuya) — cada driver valida e
 * consome apenas as chaves que conhece.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_disparo', function (Blueprint $table) {
            $table->string('driver')->default('discord')->after('nome');
            $table->json('configuracao')->nullable()->after('driver');
            $table->boolean('ativo')->default(true)->after('configuracao');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_disparo', function (Blueprint $table) {
            $table->dropColumn(['driver', 'configuracao', 'ativo']);
        });
    }
};
