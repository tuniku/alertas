<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerta_id')->constrained('alertas')->cascadeOnDelete();
            $table->dateTime('recebido_em');            // preenchido pelo servidor
            $table->dateTime('evento_em')->nullable();  // enviado pelo sistema externo
            $table->text('descricao')->nullable();
            $table->timestamps();

            $table->index(['alerta_id', 'recebido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_logs');
    }
};
