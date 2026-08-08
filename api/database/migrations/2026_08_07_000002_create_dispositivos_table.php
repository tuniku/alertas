<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens do FCM para envio de push, um por aparelho.
     *
     * O token é único na tabela mesmo entre usuários diferentes: se dois
     * usuários logam no mesmo celular, o token do Firebase é o mesmo, e o
     * controller faz updateOrCreate por ele — a segunda autenticação
     * atualiza o dono da linha em vez de duplicá-la.
     */
    public function up(): void
    {
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('plataforma', 32)->default('android');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos');
    }
};
