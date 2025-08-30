<?php

namespace App\Services;

use Carbon\Carbon;

class LoanCalculator
{
    /**
     * Gera cronograma de parcelas.
     * @param float  $pv              Valor emprestado
     * @param int    $n               Número de parcelas
     * @param float  $i               Taxa mensal (ex.: 0.10 para 10%)
     * @param string $loanType        'FIXED_ON_PRINCIPAL' ou 'AMORTIZATION_ON_BALANCE'
     * @param string|\DateTime|null $firstDueDate  Data do 1º vencimento (opcional)
     * @return array  Lista de parcelas com campos: number, due_date, principal, interest, installment, balance
     */
    public function buildSchedule(float $pv, int $n, float $i, string $loanType, $firstDueDate = null): array
    {
        $schedule = [];
        $due = $firstDueDate ? Carbon::parse($firstDueDate) : Carbon::now()->addMonthNoOverflow();

        $balance = $pv;

        if ($loanType === 'FIXED_ON_PRINCIPAL') {
            // Opção A: juros fixos sobre o principal + amortização fixa
            $amort = round($pv / $n, 2);
            $interestFixed = round($pv * $i, 2);
            $installment = round($amort + $interestFixed, 2);

            for ($k = 1; $k <= $n; $k++) {
                $interest = $interestFixed;     // constante
                $principal = ($k < $n) ? $amort : round($balance, 2); // ajusta centavos na última
                $amount = $principal + $interest;
                $balance = round($balance - $principal, 2);

                $schedule[] = [
                    'number' => $k,
                    'due_date' => $due->toDateString(),
                    'principal' => $principal,
                    'interest' => $interest,
                    'installment' => $amount,
                    'balance' => max($balance, 0.00),
                ];
                $due = $due->copy()->addMonthNoOverflow();
            }
        } else {
            // Opção B: amortização fixa + juros sobre saldo (decrescente)
            $amort = round($pv / $n, 2);

            for ($k = 1; $k <= $n; $k++) {
                $interest = round($balance * $i, 2);
                $principal = ($k < $n) ? $amort : round($balance, 2); // ajusta última
                $amount = round($principal + $interest, 2);
                $balance = round($balance - $principal, 2);

                $schedule[] = [
                    'number' => $k,
                    'due_date' => $due->toDateString(),
                    'principal' => $principal,
                    'interest' => $interest,
                    'installment' => $amount,
                    'balance' => max($balance, 0.00),
                ];
                $due = $due->copy()->addMonthNoOverflow();
            }
        }

        return $schedule;
    }
}
