<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteParcela extends Model
{
    use BelongsToUser;

    protected $table = 'ajustes_parcelas';
    
    protected $fillable = [
        'from_parcela_id',
        'to_parcela_id',
        'valor_origem',
        'fator_juros',
        'valor_destino',
        'observacoes',
        'user_id', // ← multitenancy
    ];

    protected $casts = [
        'valor_origem' => 'float',
        'valor_destino'=> 'float',
        'fator_juros'  => 'float',
    ];

    /* ==================== Relations ==================== */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(Parcela::class, 'from_parcela_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Parcela::class, 'to_parcela_id');
    }
}
