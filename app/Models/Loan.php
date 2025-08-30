<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = [
        'borrower_id','principal','installments','monthly_rate','loan_type','first_due_date','status'
    ];

    public function installments()
    {
        return $this->hasMany(LoanInstallment::class);
    }
}