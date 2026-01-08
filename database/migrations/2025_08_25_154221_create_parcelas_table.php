<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('parcelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->cascadeOnDelete();

            $table->unsignedInteger('numero');     // 1..N
            $table->date('vence_em');

            $table->decimal('valor_previsto', 14, 2); // parcela teórica
            $table->decimal('valor_pago', 14, 2)->default(0);
            $table->date('pago_em')->nullable();

            // aberta | paga | atrasada | parcial
            $table->enum('status', ['aberta','paga','atrasada','parcial'])->default('aberta');

            $table->timestamps();

            $table->unique(['emprestimo_id','numero']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('parcelas');
    }
};
