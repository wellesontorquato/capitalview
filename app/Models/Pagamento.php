<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    protected $fillable = ['parcela_id','valor','pago_em', 'banco', 'modo','observacoes'];

    protected $casts = [
        'valor'  => 'float',
        'pago_em'=> 'date',
    ];

    public function parcela(): BelongsTo {
        return $this->belongsTo(Parcela::class);
    }
}
