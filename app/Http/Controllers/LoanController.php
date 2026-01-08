<?php

// app/Http/Controllers/LoanController.php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Services\LoanCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function store(Request $request, LoanCalculator $calc)
    {
        $data = $request->validate([
            'borrower_id'   => ['nullable', 'exists:users,id'], // ajuste se outra tabela
            'principal'     => ['required', 'numeric', 'min:0.01'],
            'installments'  => ['required', 'integer', 'min:1', 'max:360'],
            'monthly_rate'  => ['required', 'numeric', 'min:0'], // ex.: 0.10 = 10%
            'loan_type'     => ['required', 'in:FIXED_ON_PRINCIPAL,AMORTIZATION_ON_BALANCE'],
            'first_due_date'=> ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($data, $calc) {
            $loan = Loan::create($data);

            $schedule = $calc->buildSchedule(
                pv: (float) $loan->principal,
                n: (int) $loan->installments,
                i: (float) $loan->monthly_rate,
                loanType: $loan->loan_type,
                firstDueDate: $loan->first_due_date
            );

            foreach ($schedule as $row) {
                LoanInstallment::create([
                    'loan_id'             => $loan->id,
                    'number'              => $row['number'],
                    'due_date'            => $row['due_date'],
                    'principal_component' => $row['principal'],
                    'interest_component'  => $row['interest'],
                    'installment_amount'  => $row['installment'],
                    'remaining_balance'   => $row['balance'],
                ]);
            }

            return response()->json([
                'loan' => $loan,
                'schedule' => $schedule
            ], 201);
        });
    }
}
