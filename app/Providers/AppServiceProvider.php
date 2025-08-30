<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define comprimento padrão para strings (evita erros em migrations antigas)
        Schema::defaultStringLength(191);

        if ($this->app->environment('production')) {
            // Força https em todas as rotas/URLs geradas
            URL::forceScheme('https');

            // Garante que Laravel respeite cabeçalhos do proxy (Railway/Heroku/etc.)
            $this->app['request']->server->set('HTTPS', true);
        }
    }
}
