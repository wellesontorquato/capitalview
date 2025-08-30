<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    use BelongsToUser; // aplica escopo global + preenche user_id

    protected $fillable = [
        'parcela_id',
        'valor',
        'pago_em',
        'banco',
        'modo',
        'observacoes',
        'user_id', // importante p/ multitenancy
    ];

    protected $casts = [
        'valor'   => 'float',
        'pago_em' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }
}
