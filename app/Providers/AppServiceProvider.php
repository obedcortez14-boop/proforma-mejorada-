<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <-- Añadimos esta importación

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
        // Forzar HTTPS si la aplicación está corriendo en entorno de producción (Railway)
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
