<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->decimal('juros_previsto', 14, 2)->default(0)->after('valor_previsto');
            $table->decimal('amort_prevista', 14, 2)->default(0)->after('juros_previsto');
            $table->decimal('saldo_restante', 14, 2)->default(0)->after('amort_prevista');
        });
    }
    public function down(): void {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->dropColumn(['juros_previsto','amort_prevista','saldo_restante']);
        });
    }
};

