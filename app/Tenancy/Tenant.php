<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class Tenant
{
    /** ID do usuário atual (ou null se não houver) */
    public static function id(): ?int
    {
        // Em console/queues não há auth(); permita override por env (opcional).
        if (App::runningInConsole()) {
            $env = env('TENANT_USER_ID');
            return $env !== null ? (int) $env : null;
        }
        return Auth::id();
    }

    /** Deve ignorar o escopo? (ex.: admin) */
    public static function bypass(): bool
    {
        $u = Auth::user();
        return (bool) ($u->is_admin ?? false);
    }
}
