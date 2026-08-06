<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurações globais do sistema, no formato chave/valor.
     *
     * Usada primeiro pelo módulo de leads (webhook do Discord e token do
     * endpoint). O formato chave/valor foi escolhido em vez de uma tabela
     * com uma coluna por configuração porque cada ajuste novo passa a ser
     * uma linha, não uma migration.
     */
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->string('chave')->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
