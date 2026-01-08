<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Helper p/ criar FK só se não existir
        $addFkIfMissing = function (string $table, string $col, string $fkName) {
            $exists = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND REFERENCED_TABLE_NAME = 'users'
                  AND REFERENCED_COLUMN_NAME = 'id'
                LIMIT 1
            ", [$table, $col]);
            if (!$exists) {
                Schema::table($table, function (Blueprint $t) use ($col, $fkName) {
                    // Some DBs ignoram nome; em MySQL você pode omitir também
                    $t->foreign($col, $fkName)->references('id')->on('users')->cascadeOnDelete();
                });
            }
        };

        /* -------- 1) Adiciona colunas (somente se faltarem) -------- */
        if (!Schema::hasColumn('clientes', 'user_id')) {
            Schema::table('clientes', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->after('id'));
        }
        if (!Schema::hasColumn('emprestimos', 'user_id')) {
            Schema::table('emprestimos', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->after('id'));
        }
        if (Schema::hasTable('parcelas') && !Schema::hasColumn('parcelas', 'user_id')) {
            Schema::table('parcelas', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->after('id'));
        }
        if (Schema::hasTable('pagamentos') && !Schema::hasColumn('pagamentos', 'user_id')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->after('id'));
        }
        if (Schema::hasTable('ajustes_parcelas') && !Schema::hasColumn('ajustes_parcelas', 'user_id')) {
            Schema::table('ajustes_parcelas', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->after('id'));
        }

        /* -------- 2) Popular (só onde estiver NULL) -------- */
        $firstUserId = DB::table('users')->value('id');

        if ($firstUserId) {
            DB::table('clientes')->whereNull('user_id')->update(['user_id' => $firstUserId]);

            DB::statement("
                UPDATE emprestimos e
                JOIN clientes c ON c.id = e.cliente_id
                SET e.user_id = c.user_id
                WHERE e.user_id IS NULL
            ");
            DB::table('emprestimos')->whereNull('user_id')->update(['user_id' => $firstUserId]);

            if (Schema::hasTable('parcelas')) {
                DB::statement("
                    UPDATE parcelas p
                    JOIN emprestimos e ON e.id = p.emprestimo_id
                    SET p.user_id = e.user_id
                    WHERE p.user_id IS NULL
                ");
                DB::table('parcelas')->whereNull('user_id')->update(['user_id' => $firstUserId]);
            }

            if (Schema::hasTable('pagamentos')) {
                DB::statement("
                    UPDATE pagamentos pg
                    JOIN parcelas p ON p.id = pg.parcela_id
                    SET pg.user_id = p.user_id
                    WHERE pg.user_id IS NULL
                ");
                DB::table('pagamentos')->whereNull('user_id')->update(['user_id' => $firstUserId]);
            }

            if (Schema::hasTable('ajustes_parcelas')) {
                DB::statement("
                    UPDATE ajustes_parcelas a
                    JOIN parcelas p ON p.id = a.from_parcela_id
                    SET a.user_id = p.user_id
                    WHERE a.user_id IS NULL
                ");
                DB::statement("
                    UPDATE ajustes_parcelas a
                    JOIN parcelas p ON p.id = a.to_parcela_id
                    SET a.user_id = p.user_id
                    WHERE a.user_id IS NULL
                ");
                DB::table('ajustes_parcelas')->whereNull('user_id')->update(['user_id' => $firstUserId]);
            }
        }

        /* -------- 3) Criar FKs só se faltarem -------- */
        $addFkIfMissing('clientes', 'user_id', 'clientes_user_id_foreign');
        $addFkIfMissing('emprestimos', 'user_id', 'emprestimos_user_id_foreign');
        if (Schema::hasTable('parcelas'))   $addFkIfMissing('parcelas', 'user_id', 'parcelas_user_id_foreign');
        if (Schema::hasTable('pagamentos')) $addFkIfMissing('pagamentos', 'user_id', 'pagamentos_user_id_foreign');
        if (Schema::hasTable('ajustes_parcelas')) $addFkIfMissing('ajustes_parcelas', 'user_id', 'ajustes_parcelas_user_id_foreign');

        /* -------- 4) (Opcional) NOT NULL quando possível -------- */
        if ($firstUserId) {
            try { Schema::table('clientes', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change()); } catch (\Throwable $e) {}
            try { Schema::table('emprestimos', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change()); } catch (\Throwable $e) {}
            if (Schema::hasTable('parcelas'))   { try { Schema::table('parcelas', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change()); } catch (\Throwable $e) {} }
            if (Schema::hasTable('pagamentos')) { try { Schema::table('pagamentos', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change()); } catch (\Throwable $e) {} }
            if (Schema::hasTable('ajustes_parcelas')) { try { Schema::table('ajustes_parcelas', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change()); } catch (\Throwable $e) {} }
        }
    }

    public function down(): void
    {
        // Remover FKs e colunas apenas se existirem
        $drop = function (string $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'user_id')) return;
            Schema::table($table, function (Blueprint $t) use ($table) {
                // tenta derrubar FK por nome conhecido; se não, por coluna
                try { $t->dropForeign($table.'_user_id_foreign'); } catch (\Throwable $e) {}
                try { $t->dropForeign(['user_id']); } catch (\Throwable $e) {}
                $t->dropColumn('user_id');
            });
        };

        $drop('ajustes_parcelas');
        $drop('pagamentos');
        $drop('parcelas');
        $drop('emprestimos');
        $drop('clientes');
    }
};
