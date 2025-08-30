<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedInteger('number');                 // 1..n
            $table->date('due_date');
            $table->decimal('principal_component', 12, 2);     // amortização do mês
            $table->decimal('interest_component', 12, 2);      // juros do mês
            $table->decimal('installment_amount', 12, 2);      // parcela
            $table->decimal('remaining_balance', 12, 2);       // saldo após pagamento
            $table->string('status')->default('open');         // open/paid/late etc.
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('loan_installments');
    }
};