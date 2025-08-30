<?php

namespace App\Models\Traits;

use App\Models\Scopes\OwnedByUser;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new OwnedByUser);

        // Preenche user_id ao criar
        static::creating(function ($model) {
            if (! $model->user_id) {
                $uid = Tenant::id();

                // fallback: se vier vinculado a emprestimo/cliente, copie do pai
                if (! $uid) {
                    foreach (['emprestimo', 'cliente'] as $rel) {
                        if (method_exists($model, $rel)) {
                            $parent = $model->$rel; // se já estiver carregado
                            if (! $parent && method_exists($model, $rel)) {
                                $parent = $model->$rel()->withoutGlobalScopes()->first();
                            }
                            if ($parent && $parent->user_id) {
                                $uid = $parent->user_id;
                                break;
                            }
                        } elseif (property_exists($model, $rel.'_id') && $model->{$rel.'_id'}) {
                            $cls = '\\App\\Models\\'.ucfirst($rel);
                            if (class_exists($cls)) {
                                $parent = $cls::withoutGlobalScopes()->find($model->{$rel.'_id'});
                                if ($parent && $parent->user_id) {
                                    $uid = $parent->user_id;
                                    break;
                                }
                            }
                        }
                    }
                }

                $model->user_id = $uid;
            }
        });
    }

    /** Filtra explicitamente por um usuário (útil para relatórios/admin). */
    public function scopeForUser(Builder $q, ?int $userId = null): Builder
    {
        $uid = $userId ?? Tenant::id();
        return $q->withoutGlobalScope(OwnedByUser::class)
                 ->where($this->getTable().'.user_id', $uid);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}