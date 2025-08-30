<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteParcela extends Model
{
    protected $table = 'ajustes_parcelas';
    
    protected $fillable = ['from_parcela_id','to_parcela_id','valor_origem','fator_juros','valor_destino','observacoes'];

    protected $casts = [
        'valor_origem' => 'float',
        'valor_destino'=> 'float',
        'fator_juros'  => 'float',
    ];

    public function origem(): BelongsTo {
        return $this->belongsTo(Parcela::class, 'from_parcela_id');
    }
    public function destino(): BelongsTo {
        return $this->belongsTo(Parcela::class, 'to_parcela_id');
    }
}
