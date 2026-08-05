<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de cada tentativa de notificação. Sem isto, quando um alerta
 * "não chegar no Discord" não há como saber se o job nem rodou, se a
 * URL do webhook está errada ou se o Discord recusou a mensagem — o
 * erro ficaria só no log de texto do worker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacao_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerta_ativo_id')->constrained('alertas_ativos')->cascadeOnDelete();
            $table->foreignId('tipo_disparo_id')->constrained('tipos_disparo')->cascadeOnDelete();
            $table->string('driver');
            $table->boolean('sucesso');
            $table->text('erro')->nullable();
            $table->timestamps();

            $table->index(['alerta_ativo_id', 'sucesso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacao_logs');
    }
};
