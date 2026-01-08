<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Armazene somente dígitos (11). Unique permite múltiplos NULLs no MySQL.
            $table->char('cpf', 11)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nome padrão do índice unique: users_cpf_unique
            $table->dropUnique('users_cpf_unique');
            $table->dropColumn('cpf');
        });
    }
};
