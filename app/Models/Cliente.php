<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use BelongsToUser; // ← escopo global + preenchimento automático de user_id

    // Libera os novos campos para preenchimento em massa
    protected $fillable = [
        'nome',
        'apelido',
        'whatsapp',
        'email',
        'cpf',
        'rg',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'observacoes',
        'user_id',
    ];

    protected $casts = [
        'observacoes' => 'string',
    ];

    /* ======================== Relations ======================== */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emprestimos(): HasMany
    {
        return $this->hasMany(Emprestimo::class);
    }

    /* ======================== Scopes =========================== */

    /**
     * Busca simples por nome, apelido, e-mail, CPF/WhatsApp (ignorando máscara).
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $term);

        return $query->where(function ($q) use ($term, $digits) {
            $q->where('nome', 'like', "%{$term}%")
              ->orWhere('apelido', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('cpf', 'like', "%{$digits}%")
              ->orWhere('whatsapp', 'like', "%{$digits}%");
        });
    }

    /* ==================== Mutators / Accessors ================= */

    // Normaliza para dígitos; formatação fica por conta dos accessors
    public function setWhatsappAttribute($value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        $this->attributes['whatsapp'] = $digits ?: null;
    }

    public function getWhatsappMaskedAttribute(): ?string
    {
        $w = $this->attributes['whatsapp'] ?? null;
        if (!$w) return null;

        // tenta formatar BR com/sem código do país
        if (strlen($w) >= 12) { // ex: 5511999999999
            $cc  = substr($w, 0, 2);
            $ddd = substr($w, 2, 2);
            $rest = substr($w, 4);
            [$p1, $p2] = (strlen($rest) === 9)
                ? [substr($rest, 0, 5), substr($rest, 5)]
                : [substr($rest, 0, 4), substr($rest, 4)];
            return "+{$cc} ({$ddd}) {$p1}-{$p2}";
        }

        if (strlen($w) >= 10) { // ex: 11999999999
            $ddd  = substr($w, 0, 2);
            $rest = substr($w, 2);
            [$p1, $p2] = (strlen($rest) === 9)
                ? [substr($rest, 0, 5), substr($rest, 5)]
                : [substr($rest, 0, 4), substr($rest, 4)];
            return "({$ddd}) {$p1}-{$p2}";
        }

        return $w;
    }

    public function setCpfAttribute($value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        $this->attributes['cpf'] = $digits ?: null;
    }

    public function getCpfMaskedAttribute(): ?string
    {
        $d = $this->attributes['cpf'] ?? null;
        if (!$d) return null;
        $d = str_pad(preg_replace('/\D+/', '', $d), 11, '0', STR_PAD_LEFT);
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    public function setCepAttribute($value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        $this->attributes['cep'] = $digits ?: null;
    }

    public function getCepMaskedAttribute(): ?string
    {
        $d = $this->attributes['cep'] ?? null;
        if (!$d) return null;
        $d = str_pad(preg_replace('/\D+/', '', $d), 8, '0', STR_PAD_LEFT);
        return substr($d, 0, 5) . '-' . substr($d, 5);
    }

    /**
     * Endereço em linha (útil para a listagem/pill).
     */
    public function getEnderecoLinhaAttribute(): ?string
    {
        $parts = [];

        if ($this->logradouro) {
            $base = $this->logradouro;
            if ($this->numero) $base .= ", {$this->numero}";
            $parts[] = $base;
        }

        if ($this->complemento) $parts[] = $this->complemento;
        if ($this->bairro)      $parts[] = $this->bairro;

        $cityUf = trim(($this->cidade ?: '') . ($this->uf ? '/' . $this->uf : ''));
        if ($cityUf !== '')     $parts[] = $cityUf;

        if ($this->cep_masked)  $parts[] = $this->cep_masked;

        $line = implode(' · ', array_filter($parts));
        return $line !== '' ? $line : null;
    }

    // Se quiser expor os campos mascarados por padrão nas respostas JSON, descomente:
    // protected $appends = ['whatsapp_masked', 'cpf_masked', 'cep_masked', 'endereco_linha'];
}
