<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_ativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerta_id')->constrained('alertas')->cascadeOnDelete();
            $table->boolean('ativo')->default(true);
            $table->foreignId('fechado_por')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('fechado_em')->nullable();

            // Ao passar desta data, o registro deixa de bloquear a criação
            // de um novo alerta ativo para o mesmo alerta.
            $table->dateTime('expira_em')->nullable();

            $table->timestamps(); // created_at = criação, updated_at = última atualização

            $table->index(['alerta_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_ativos');
    }
};
