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
        // Helpers globales (formatDescripcionProforma, etc.) disponibles para
        // todas las vistas y controladores de la aplicación.
        require_once app_path('Support/helpers.php');
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
