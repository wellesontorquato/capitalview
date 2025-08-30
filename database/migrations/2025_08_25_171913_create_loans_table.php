<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->nullable()->constrained('users'); // ou sua tabela de clientes
            $table->decimal('principal', 12, 2);   // PV
            $table->unsignedInteger('installments'); // n
            $table->decimal('monthly_rate', 8, 6);   // ex.: 0.10 para 10%
            $table->enum('loan_type', ['FIXED_ON_PRINCIPAL', 'AMORTIZATION_ON_BALANCE']); 
            $table->date('first_due_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('loans');
    }
};
