<?php

namespace Lexroute;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class LexrouteServiceProvider extends ServiceProvider
{


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $lang = $this->app->getLocale();
        $pathlang = 'lang'.DIRECTORY_SEPARATOR.$lang;
        $this->publishes([
            __DIR__.'/resources/lang' => resource_path($pathlang),
        ],'lexroute.lang');

        $source = realpath($raw = __DIR__ . '/config/lexroute.php') ?: $raw;
        $this->publishes([$source => config_path('lexroute.php')],'lexroute.config');
        (config('lexroute') !== null)?:$this->mergeConfigFrom($source, 'lexroute');

        /**
         * Register commands, so you may execute them using the Artisan CLI.
         */
        if($this->app->runningInConsole()) {
            $this->commands([
                RouteUpdate::class,
                RouteMake::class
            ]);
        }

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(RouteUpdate::class, function ($app) {
            $app->bind('Lexroute\Contracts\LexrouteException',function(){
                return new LexrouteException();
            });
            $e = $app->make('Lexroute\Contracts\LexrouteException');
            $routerupdate = new RouteUpdate($e);
            return $routerupdate;
        });
        $this->app->singleton(RouteMake::class, function ($app) {
            $app->bind('Lexroute\Contracts\LexrouteException',function(){
                return new LexrouteException();
            });
            $e = $app->make('Lexroute\Contracts\LexrouteException');
            $routermake = new RouteMake($e);
            return $routermake;
        });
    }
}
