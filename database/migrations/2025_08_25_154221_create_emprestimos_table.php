<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('emprestimos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

            $table->decimal('valor_principal', 14, 2);
            // taxa por período (ex.: 0.02 = 2% a.m.)
            $table->decimal('taxa_periodo', 7, 6)->default(0);

            // simples | composto
            $table->enum('regime_juros', ['simples','composto'])->default('simples');

            // define o período da taxa: mensal | semanal | diario
            $table->enum('capitalizacao', ['mensal','semanal','diario'])->default('mensal');

            // livre | price | sac
            $table->enum('metodo_amortizacao', ['livre','price','sac'])->default('livre');

            $table->unsignedInteger('qtd_parcelas')->nullable();
            $table->date('primeiro_vencimento')->nullable();

            // ativo | quitado | inadimplente
            $table->enum('status', ['ativo','quitado','inadimplente'])->default('ativo');

            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('emprestimos');
    }
};
