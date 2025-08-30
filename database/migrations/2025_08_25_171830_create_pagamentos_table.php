<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_pagamentos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->decimal('valor', 14, 2);
            $table->date('pago_em')->nullable();
            $table->enum('modo', ['juros','parcial','total'])->default('parcial');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('pagamentos');
    }
};

