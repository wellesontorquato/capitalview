<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ===== EMPRESTIMOS =====
        Schema::table('emprestimos', function (Blueprint $table) {
            if (Schema::hasColumn('emprestimos', 'regime_juros')) {
                $table->dropColumn('regime_juros');
            }
            if (Schema::hasColumn('emprestimos', 'capitalizacao')) {
                $table->dropColumn('capitalizacao');
            }
            if (Schema::hasColumn('emprestimos', 'metodo_amortizacao')) {
                $table->dropColumn('metodo_amortizacao');
            }
            // se você tinha 'taxa_mensal' redundante, pode dropar também
            if (Schema::hasColumn('emprestimos', 'taxa_mensal')) {
                $table->dropColumn('taxa_mensal');
            }
        });

        // ===== PARCELAS =====
        Schema::table('parcelas', function (Blueprint $table) {
            if (Schema::hasColumn('parcelas', 'vence_em')) {
                $table->dropColumn('vence_em');
            }
            if (Schema::hasColumn('parcelas', 'valor_previsto')) {
                $table->dropColumn('valor_previsto');
            }
            if (Schema::hasColumn('parcelas', 'juros_previsto')) {
                $table->dropColumn('juros_previsto');
            }
            if (Schema::hasColumn('parcelas', 'amort_prevista')) {
                $table->dropColumn('amort_prevista');
            }
            if (Schema::hasColumn('parcelas', 'saldo_restante')) {
                $table->dropColumn('saldo_restante');
            }
        });
    }

    public function down(): void
    {
        // Recria as colunas antigas, mas sem backfill (faça apenas se realmente precisar de rollback)
        Schema::table('emprestimos', function (Blueprint $table) {
            if (!Schema::hasColumn('emprestimos', 'regime_juros')) {
                $table->string('regime_juros')->nullable();
            }
            if (!Schema::hasColumn('emprestimos', 'capitalizacao')) {
                $table->string('capitalizacao')->nullable();
            }
            if (!Schema::hasColumn('emprestimos', 'metodo_amortizacao')) {
                $table->string('metodo_amortizacao')->nullable();
            }
            if (!Schema::hasColumn('emprestimos', 'taxa_mensal')) {
                $table->decimal('taxa_mensal', 8, 6)->nullable();
            }
        });

        Schema::table('parcelas', function (Blueprint $table) {
            if (!Schema::hasColumn('parcelas', 'vence_em')) {
                $table->date('vence_em')->nullable();
            }
            if (!Schema::hasColumn('parcelas', 'valor_previsto')) {
                $table->decimal('valor_previsto', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('parcelas', 'juros_previsto')) {
                $table->decimal('juros_previsto', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('parcelas', 'amort_prevista')) {
                $table->decimal('amort_prevista', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('parcelas', 'saldo_restante')) {
                $table->decimal('saldo_restante', 12, 2)->default(0);
            }
        });
    }
};

