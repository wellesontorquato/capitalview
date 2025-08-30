<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parcela extends Model
{
    protected $fillable = [
        'emprestimo_id',
        'numero',
        'vencimento',            // data do vencimento
        'valor_amortizacao',     // componente de principal
        'valor_juros',           // componente de juros
        'valor_parcela',         // valor total da parcela (previsto)
        'saldo_devedor',         // saldo após pagamento
        'valor_pago',
        'pago_em',
        'status',
    ];

    protected $casts = [
        'vencimento'         => 'date',
        'pago_em'            => 'date',
        'valor_amortizacao'  => 'decimal:2',
        'valor_juros'        => 'decimal:2',
        'valor_parcela'      => 'decimal:2',
        'saldo_devedor'      => 'decimal:2',
        'valor_pago'         => 'decimal:2',
    ];

    public function emprestimo(): BelongsTo
    {
        return $this->belongsTo(Emprestimo::class);
    }

    // === RELAÇÕES ===
    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function ajustesOrigem(): HasMany
    {
        return $this->hasMany(AjusteParcela::class, 'from_parcela_id');
    }

    public function ajustesDestino(): HasMany
    {
        return $this->hasMany(AjusteParcela::class, 'to_parcela_id');
    }

    // === DERIVADOS ===

    /** Total efetivamente pago (histórico de pagamentos ou fallback no campo). */
    public function getTotalPagoAttribute(): float
    {
        $hist = (float) $this->pagamentos()->sum('valor');
        return round($hist > 0 ? $hist : (float) ($this->valor_pago ?? 0), 2);
    }

    /**
     * Valor da parcela ajustado por transferências (ajustes) — novo nome.
     * Base: valor_parcela - saídas + entradas.
     */
    public function getValorParcelaAjustadaAttribute(): float
    {
        $out = (float) $this->ajustesOrigem()->sum('valor_origem');
        $in  = (float) $this->ajustesDestino()->sum('valor_destino');
        return round(((float) $this->valor_parcela - $out + $in), 2);
    }

    /**
     * Alias de compatibilidade com telas antigas (antes usava "valor_previsto_ajustado").
     */
    public function getValorPrevistoAjustadoAttribute(): float
    {
        return $this->valor_parcela_ajustada;
    }

    /** Saldo atual dessa parcela (apenas da parcela, não do empréstimo). */
    public function getSaldoAtualAttribute(): float
    {
        return max(0, $this->valor_parcela_ajustada - $this->total_pago);
    }
}
