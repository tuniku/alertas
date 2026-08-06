<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Id do EVENTO no FunnelsFlow (ex.: "del-a1b2c3d4e5f6"), não do
            // negócio. É único para garantir idempotência: se a plataforma
            // reenviar o mesmo evento por timeout ou retry, o segundo POST
            // não cria um lead duplicado.
            $table->string('evento_id')->unique();
            $table->string('evento');

            // Id do negócio. Não é único de propósito: um mesmo negócio pode
            // gerar vários eventos no futuro (se passarmos a aceitar
            // deal.updated, por exemplo).
            $table->string('deal_id')->index();
            $table->unsignedInteger('numero')->nullable();
            $table->string('titulo')->nullable();

            $table->decimal('valor', 15, 2)->nullable();
            $table->string('moeda', 8)->nullable();
            $table->string('status')->nullable();

            $table->string('pipeline_nome')->nullable();
            $table->string('stage_nome')->nullable();
            $table->string('owner_nome')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('origem')->nullable();
            $table->json('tags')->nullable();

            $table->string('pessoa_nome')->nullable();
            $table->string('pessoa_email')->nullable();
            $table->string('pessoa_telefone')->nullable();

            $table->string('organizacao_nome')->nullable();
            $table->string('organizacao_dominio')->nullable();

            $table->string('url')->nullable();

            // Data em que o negócio foi criado na origem, distinta da data
            // em que este sistema recebeu o webhook — se houver fila ou
            // retry na plataforma, as duas divergem.
            $table->timestamp('criado_em_origem')->nullable();
            $table->timestamp('recebido_em');

            // Payload completo. Guardar o original custa pouco e evita
            // perder informação que hoje não é exibida mas pode importar
            // depois (utm, campos personalizados, createdBy).
            $table->json('payload');

            $table->timestamps();

            $table->index('recebido_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
