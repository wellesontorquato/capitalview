<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration {
    public function up(): void
    {
        // ===== EMPRESTIMOS =====
        Schema::table('emprestimos', function (Blueprint $table) {
            // novo enum/tipo_calculo
            if (!Schema::hasColumn('emprestimos', 'tipo_calculo')) {
                $table->string('tipo_calculo')->nullable()->after('taxa_periodo');
            }

            // garantir taxa_periodo (alguns projetos usam taxa_mensal)
            if (!Schema::hasColumn('emprestimos', 'taxa_periodo') && Schema::hasColumn('emprestimos', 'taxa_mensal')) {
                $table->decimal('taxa_periodo', 8, 6)->nullable()->after('valor_principal');
                // backfill rápido de taxa_mensal -> taxa_periodo
                DB::statement("UPDATE emprestimos SET taxa_periodo = taxa_mensal WHERE taxa_periodo IS NULL");
            } elseif (!Schema::hasColumn('emprestimos', 'taxa_periodo')) {
                $table->decimal('taxa_periodo', 8, 6)->nullable()->after('valor_principal');
            }

            // garantir qtd_parcelas e primeiro_vencimento existem (geralmente já existem)
            if (!Schema::hasColumn('emprestimos', 'qtd_parcelas')) {
                $table->unsignedInteger('qtd_parcelas')->nullable()->after('valor_principal');
            }
            if (!Schema::hasColumn('emprestimos', 'primeiro_vencimento')) {
                $table->date('primeiro_vencimento')->nullable()->after('qtd_parcelas');
            }
        });

        // BACKFILL: mapear metodo_amortizacao antigo -> tipo_calculo novo
        // 'sac' -> 'AMORTIZATION_ON_BALANCE'
        // 'price' ou 'livre' -> 'FIXED_ON_PRINCIPAL'
        if (Schema::hasColumn('emprestimos', 'metodo_amortizacao')) {
            DB::table('emprestimos')->whereNull('tipo_calculo')->update(['tipo_calculo' => DB::raw("
                CASE 
                    WHEN metodo_amortizacao = 'sac' THEN 'AMORTIZATION_ON_BALANCE'
                    ELSE 'FIXED_ON_PRINCIPAL'
                END
            ")]);
        } else {
            DB::table('emprestimos')->whereNull('tipo_calculo')->update(['tipo_calculo' => 'FIXED_ON_PRINCIPAL']);
        }

        // ===== PARCELAS =====
        Schema::table('parcelas', function (Blueprint $table) {
            if (!Schema::hasColumn('parcelas', 'vencimento') && Schema::hasColumn('parcelas', 'vence_em')) {
                $table->date('vencimento')->nullable()->after('numero');
            } elseif (!Schema::hasColumn('parcelas', 'vencimento')) {
                $table->date('vencimento')->nullable()->after('numero');
            }

            if (!Schema::hasColumn('parcelas', 'valor_parcela')) {
                $table->decimal('valor_parcela', 12, 2)->default(0)->after('vencimento');
            }
            if (!Schema::hasColumn('parcelas', 'valor_juros')) {
                $table->decimal('valor_juros', 12, 2)->default(0)->after('valor_parcela');
            }
            if (!Schema::hasColumn('parcelas', 'valor_amortizacao')) {
                $table->decimal('valor_amortizacao', 12, 2)->default(0)->after('valor_juros');
            }
            if (!Schema::hasColumn('parcelas', 'saldo_devedor')) {
                $table->decimal('saldo_devedor', 12, 2)->default(0)->after('valor_amortizacao');
            }
        });

        // BACKFILL seguro de datas: vence_em -> vencimento
        if (Schema::hasColumn('parcelas', 'vence_em')) {
            // limpa vencimento inválido
            DB::table('parcelas')
                ->where(function($q){
                    $q->whereNull('vencimento')
                      ->orWhere('vencimento', '0000-00-00');
                })
                ->update(['vencimento' => null]);

            // atualiza em chunks, tratando zero-date e datetime
            DB::table('parcelas')
                ->select('id','vence_em')
                ->whereNotNull('vence_em')
                ->orderBy('id')
                ->chunkById(1000, function ($rows) {
                    foreach ($rows as $r) {
                        $raw = (string) $r->vence_em;

                        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
                            DB::table('parcelas')->where('id', $r->id)->update(['vencimento' => null]);
                            continue;
                        }

                        try {
                            $d = Carbon::parse($raw);
                            if ($d->year < 1900 || $d->year > 2100) {
                                DB::table('parcelas')->where('id', $r->id)->update(['vencimento' => null]);
                                continue;
                            }
                            DB::table('parcelas')->where('id', $r->id)->update(['vencimento' => $d->toDateString()]);
                        } catch (\Throwable $e) {
                            DB::table('parcelas')->where('id', $r->id)->update(['vencimento' => null]);
                        }
                    }
                });
        }

        // BACKFILL numéricos (seguros)
        if (Schema::hasColumn('parcelas', 'valor_previsto')) {
            DB::statement("UPDATE parcelas SET valor_parcela = valor_previsto WHERE valor_parcela = 0 AND valor_previsto IS NOT NULL");
        }
        if (Schema::hasColumn('parcelas', 'juros_previsto')) {
            DB::statement("UPDATE parcelas SET valor_juros = juros_previsto WHERE valor_juros = 0 AND juros_previsto IS NOT NULL");
        }
        if (Schema::hasColumn('parcelas', 'amort_prevista')) {
            DB::statement("UPDATE parcelas SET valor_amortizacao = amort_prevista WHERE valor_amortizacao = 0 AND amort_prevista IS NOT NULL");
        }
        if (Schema::hasColumn('parcelas', 'saldo_restante')) {
            DB::statement("UPDATE parcelas SET saldo_devedor = saldo_restante WHERE saldo_devedor = 0 AND saldo_restante IS NOT NULL");
        }
    }

    public function down(): void
    {
        // rollback simples: não remove novas colunas; tenta repopular antigas se existirem
        if (Schema::hasColumn('parcelas', 'vence_em') && Schema::hasColumn('parcelas', 'vencimento')) {
            DB::statement("UPDATE parcelas SET vence_em = COALESCE(vence_em, vencimento)");
        }
        if (Schema::hasColumn('parcelas', 'valor_previsto') && Schema::hasColumn('parcelas', 'valor_parcela')) {
            DB::statement("UPDATE parcelas SET valor_previsto = COALESCE(valor_previsto, valor_parcela)");
        }
        if (Schema::hasColumn('parcelas', 'juros_previsto') && Schema::hasColumn('parcelas', 'valor_juros')) {
            DB::statement("UPDATE parcelas SET juros_previsto = COALESCE(juros_previsto, valor_juros)");
        }
        if (Schema::hasColumn('parcelas', 'amort_prevista') && Schema::hasColumn('parcelas', 'valor_amortizacao')) {
            DB::statement("UPDATE parcelas SET amort_prevista = COALESCE(amort_prevista, valor_amortizacao)");
        }
        if (Schema::hasColumn('parcelas', 'saldo_restante') && Schema::hasColumn('parcelas', 'saldo_devedor')) {
            DB::statement("UPDATE parcelas SET saldo_restante = COALESCE(saldo_restante, saldo_devedor)");
        }
    }
};
