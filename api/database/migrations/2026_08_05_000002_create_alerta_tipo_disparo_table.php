<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Um alerta pode notificar em vários canais ao mesmo tempo (ex.: um
 * alerta crítico que posta no Discord e acende a lâmpada do escritório),
 * então a FK única em alertas dá lugar a esta tabela de ligação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_tipo_disparo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerta_id')->constrained('alertas')->cascadeOnDelete();
            $table->foreignId('tipo_disparo_id')->constrained('tipos_disparo')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['alerta_id', 'tipo_disparo_id']);
        });

        // Preserva vínculos já cadastrados antes de remover a coluna antiga.
        if (Schema::hasColumn('alertas', 'tipo_disparo_id')) {
            DB::table('alertas')
                ->whereNotNull('tipo_disparo_id')
                ->orderBy('id')
                ->each(function ($alerta) {
                    DB::table('alerta_tipo_disparo')->insert([
                        'alerta_id' => $alerta->id,
                        'tipo_disparo_id' => $alerta->tipo_disparo_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table('alertas', function (Blueprint $table) {
                $table->dropForeign(['tipo_disparo_id']);
                $table->dropColumn('tipo_disparo_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->foreignId('tipo_disparo_id')->nullable()
                ->constrained('tipos_disparo')->nullOnDelete();
        });

        Schema::dropIfExists('alerta_tipo_disparo');
    }
};
