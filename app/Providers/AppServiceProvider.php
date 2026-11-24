<?php

namespace App\Providers;

use App\Adapters\ListaBuenosTraviesosAdapter;
use App\Contracts\ComportamientoGateway;
use App\Repositories\CartaRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Inyección de dependencias: aquí se decide qué implementación usa cada puerto. */
    public function register(): void
    {
        $this->app->singleton(CartaRepository::class, fn () => new CartaRepository(
            (string) config('polonorte.cartas_path')
        ));

        $this->app->bind(ComportamientoGateway::class, fn () => new ListaBuenosTraviesosAdapter(
            endpoint: (string) config('polonorte.soap.lista_url'),
            timeoutSegundos: (float) config('polonorte.soap.timeout'),
            intentos: (int) config('polonorte.soap.intentos'),
            soapAction: (string) config('polonorte.soap.action'),
        ));
    }

    public function boot(): void
    {
    }
}
