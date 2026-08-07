<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca quais alertas aparecem no aplicativo Android.
     *
     * Default false de propósito: alertas que já existem não passam a
     * aparecer no celular sem alguém decidir isso explicitamente.
     */
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->boolean('disponivel_app')->default(false)->after('expiracao_minutos');

            // O app filtra por esta coluna em toda listagem que faz.
            $table->index('disponivel_app');
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropIndex(['disponivel_app']);
            $table->dropColumn('disponivel_app');
        });
    }
};
