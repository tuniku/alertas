<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estrutura de referência: os tipos de disparo (WhatsApp, e-mail etc.)
     * serão detalhados em uma etapa futura.
     */
    public function up(): void
    {
        Schema::create('tipos_disparo', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_disparo');
    }
};
