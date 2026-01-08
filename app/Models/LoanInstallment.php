<?php

// app/Models/LoanInstallment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id','number','due_date','principal_component','interest_component','installment_amount','remaining_balance','status'
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
