<?php

// database/migrations/2025_08_25_120000_alter_emprestimos_drop_old_fields.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('emprestimos', function (Blueprint $table) {
            if (Schema::hasColumn('emprestimos','regime_juros')) {
                $table->string('regime_juros')->nullable()->change();
            }
            if (Schema::hasColumn('emprestimos','capitalizacao')) {
                $table->string('capitalizacao')->nullable()->change();
            }
            if (Schema::hasColumn('emprestimos','metodo_amortizacao')) {
                $table->string('metodo_amortizacao')->nullable()->change();
            }
            // adiciona o campo novo, se ainda não existir
            if (!Schema::hasColumn('emprestimos','tipo_calculo')) {
                $table->string('tipo_calculo')->after('taxa_periodo');
            }
        });
    }

    public function down(): void {
        Schema::table('emprestimos', function (Blueprint $table) {
            // opcional: sem rollback estrito
        });
    }
};
