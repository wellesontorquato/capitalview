<?php

namespace App\Models\Scopes;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OwnedByUser implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Tenant::bypass()) return;

        $uid = Tenant::id();
        if ($uid) {
            $builder->where($model->getTable().'.user_id', $uid);
        }
        // Sem usuário (p.ex. artisan sem TENANT_USER_ID): não filtra.
        // Se preferir bloquear, troque para where('user_id', -1).
    }
}
