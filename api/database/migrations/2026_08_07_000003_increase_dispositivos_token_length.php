<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O token do FCM normalmente fica por volta de 160-200 caracteres,
     * mas o Google não documenta um teto fixo — já foram vistos casos
     * bem mais longos. Em vez de arriscar um "Data too long for column"
     * (erro que o app, do jeito que registrarDispositivo() estava antes
     * desta correção, engolia em silêncio), a coluna passa a comportar
     * até 512.
     */
    public function up(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            // Sem ->unique() aqui: o índice único já existe desde a
            // migration que criou a tabela, e o change() do Laravel trata
            // ->unique() como "crie um índice novo", não como "preserve o
            // que já está lá" — o que resulta em "Duplicate key name".
            // Alterar só o tamanho não mexe no índice.
            $table->string('token', 512)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->string('token', 255)->change();
        });
    }
};
