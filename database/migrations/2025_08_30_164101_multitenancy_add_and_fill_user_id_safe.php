<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- 1) Adiciona colunas SEM FK, nullable ---------- */

        if (!Schema::hasColumn('clientes', 'user_id')) {
            Schema::table('clientes', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }

        if (!Schema::hasColumn('emprestimos', 'user_id')) {
            Schema::table('emprestimos', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }

        if (!Schema::hasColumn('parcelas', 'user_id')) {
            Schema::table('parcelas', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }

        if (Schema::hasTable('pagamentos') && !Schema::hasColumn('pagamentos', 'user_id')) {
            Schema::table('pagamentos', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }

        // nome que você usou no model foi 'ajustes_parcelas'
        if (Schema::hasTable('ajustes_parcelas') && !Schema::hasColumn('ajustes_parcelas', 'user_id')) {
            Schema::table('ajustes_parcelas', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }

        /* ---------- 2) Popular user_id existentes ---------- */

        // pega um usuário "dono padrão" (se existir)
        $firstUserId = DB::table('users')->value('id');

        // CLIENTES: se estiver tudo nulo, joga no primeiro user
        if ($firstUserId) {
            DB::table('clientes')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        // EMPRESTIMOS: tenta herdar do cliente; se continuar nulo, cai pro firstUserId
        DB::statement("
            UPDATE emprestimos e
            JOIN clientes c ON c.id = e.cliente_id
            SET e.user_id = c.user_id
            WHERE e.user_id IS NULL AND c.user_id IS NOT NULL
        ");
        if ($firstUserId) {
            DB::table('emprestimos')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        // PARCELAS: herda do emprestimo
        DB::statement("
            UPDATE parcelas p
            JOIN emprestimos e ON e.id = p.emprestimo_id
            SET p.user_id = e.user_id
            WHERE p.user_id IS NULL AND e.user_id IS NOT NULL
        ");
        if ($firstUserId) {
            DB::table('parcelas')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        // PAGAMENTOS: herda da parcela
        if (Schema::hasTable('pagamentos')) {
            DB::statement("
                UPDATE pagamentos pg
                JOIN parcelas p ON p.id = pg.parcela_id
                SET pg.user_id = p.user_id
                WHERE pg.user_id IS NULL AND p.user_id IS NOT NULL
            ");
            if ($firstUserId) {
                DB::table('pagamentos')->whereNull('user_id')->update(['user_id' => $firstUserId]);
            }
        }

        // AJUSTES_PARCELAS: herda da origem; se nulo, tenta destino; se nulo, cai pro firstUserId
        if (Schema::hasTable('ajustes_parcelas')) {
            DB::statement("
                UPDATE ajustes_parcelas a
                JOIN parcelas p ON p.id = a.from_parcela_id
                SET a.user_id = p.user_id
                WHERE a.user_id IS NULL AND p.user_id IS NOT NULL
            ");
            DB::statement("
                UPDATE ajustes_parcelas a
                JOIN parcelas p ON p.id = a.to_parcela_id
                SET a.user_id = p.user_id
                WHERE a.user_id IS NULL AND p.user_id IS NOT NULL
            ");
            if ($firstUserId) {
                DB::table('ajustes_parcelas')->whereNull('user_id')->update(['user_id' => $firstUserId]);
            }
        }

        /* ---------- 3) Só agora cria as FKs e (opcional) NOT NULL ---------- */

        // clientes
        Schema::table('clientes', function (Blueprint $t) {
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // emprestimos
        Schema::table('emprestimos', function (Blueprint $t) {
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // parcelas
        Schema::table('parcelas', function (Blueprint $t) {
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // pagamentos
        if (Schema::hasTable('pagamentos')) {
            Schema::table('pagamentos', function (Blueprint $t) {
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // ajustes_parcelas
        if (Schema::hasTable('ajustes_parcelas')) {
            Schema::table('ajustes_parcelas', function (Blueprint $t) {
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Se você QUISER travar como NOT NULL, só faça se houver usuário:
        if ($firstUserId) {
            Schema::table('clientes', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change());
            Schema::table('emprestimos', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change());
            Schema::table('parcelas', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change());
            if (Schema::hasTable('pagamentos')) {
                Schema::table('pagamentos', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change());
            }
            if (Schema::hasTable('ajustes_parcelas')) {
                Schema::table('ajustes_parcelas', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(false)->change());
            }
        }
    }

    public function down(): void
    {
        // Remover FKs e colunas, se existirem
        foreach (['clientes','emprestimos','parcelas','pagamentos','ajustes_parcelas'] as $tbl) {
            if (!Schema::hasTable($tbl) || !Schema::hasColumn($tbl, 'user_id')) continue;
            Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                // nome do índice pode variar; use dropForeign com array
                try { $t->dropForeign([$tbl.'_user_id_foreign']); } catch (\Throwable $e) {}
                try { $t->dropForeign(['user_id']); } catch (\Throwable $e) {}
                $t->dropColumn('user_id');
            });
        }
    }
};
