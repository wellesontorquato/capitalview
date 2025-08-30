<?php

namespace App\Models;

use App\Services\LoanCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Emprestimo extends Model
{
    protected $fillable = [
        'cliente_id',
        'valor_principal',
        'qtd_parcelas',
        'taxa_periodo',          // decimal mensal (ex.: 0.10 = 10% a.m.)
        'tipo_calculo',          // 'FIXED_ON_PRINCIPAL' | 'AMORTIZATION_ON_BALANCE'
        'primeiro_vencimento',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'valor_principal'     => 'decimal:2',
        'taxa_periodo'        => 'decimal:6',
        'primeiro_vencimento' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(Parcela::class)->orderBy('numero');
    }

    /**
     * Gera cronograma e salva Parcelas segundo as DUAS opções:
     *  - FIXED_ON_PRINCIPAL: parcela constante = amortização fixa + juros fixos sobre o principal
     *  - AMORTIZATION_ON_BALANCE: amortização fixa + juros sobre saldo (parcelas decrescentes)
     *
     * Usa LoanCalculator para manter regra única entre front e back.
     *
     * @param \App\Services\LoanCalculator|null $calc
     * @return void
     */
    public function gerarCronograma(?LoanCalculator $calc = null): void
    {
        // dados mínimos
        if (!$this->qtd_parcelas || !$this->valor_principal) {
            return;
        }

        $calc ??= app(LoanCalculator::class);

        $schedule = $calc->buildSchedule(
            pv: (float) $this->valor_principal,
            n:  (int)   $this->qtd_parcelas,
            i:  (float) $this->taxa_periodo,
            loanType:   (string) $this->tipo_calculo,
            firstDueDate: $this->primeiro_vencimento ?: Carbon::now()->addMonthNoOverflow()->toDateString()
        );

        // cuidado: em produção, verifique pagamentos antes de apagar
        $this->parcelas()->delete();

        foreach ($schedule as $row) {
            $this->parcelas()->create([
                'numero'            => $row['number'],
                'vencimento'        => $row['due_date'],
                'valor_amortizacao' => $row['principal'],
                'valor_juros'       => $row['interest'],
                'valor_parcela'     => $row['installment'],
                'saldo_devedor'     => $row['balance'],
                'status'            => 'aberta',
            ]);
        }
    }
}
