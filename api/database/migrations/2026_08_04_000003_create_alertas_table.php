<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projeto_id')->constrained('projetos')->cascadeOnDelete();

            // Código único que o sistema externo usa para disparar o alerta.
            // Único globalmente porque o endpoint de disparo é público e não
            // carrega contexto de projeto nesta etapa.
            $table->string('codigo')->unique();

            $table->string('nome');
            $table->unsignedTinyInteger('importancia')->default(0); // 0 a 10
            $table->foreignId('tipo_disparo_id')->nullable()
                ->constrained('tipos_disparo')->nullOnDelete();

            // TTL da deduplicação: minutos até o alerta ativo expirar.
            // Null = nunca expira (bloqueia duplicados até fechamento manual).
            $table->unsignedInteger('expiracao_minutos')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
