<?php

namespace App\Providers;

use App\Models\Configuracion;
use App\Services\Permisos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        foreach (Permisos::habilidades() as $habilidad) {

            Gate::define(
                $habilidad,
                fn ($usuario) => Permisos::puede($usuario, $habilidad)
            );
        }

        // Logo disponible en todas las vistas
        View::share(
            'logo',
            Configuracion::logo()
        );
    }
}
