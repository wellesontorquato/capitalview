<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_ajustes_parcelas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ajustes_parcelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->foreignId('to_parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->decimal('valor_origem', 14, 2);        // quanto saiu da origem
            $table->decimal('fator_juros', 10, 6)->default(0); // (1+i)^n - 1, se composto
            $table->decimal('valor_destino', 14, 2);       // quanto chega no destino (com ou sem juros)
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('ajustes_parcelas');
    }
};

