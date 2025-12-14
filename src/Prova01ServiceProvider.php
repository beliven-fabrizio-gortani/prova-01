<?php

namespace Beliven\Prova01;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Beliven\Prova01\Commands\Prova01Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Beliven\Prova01\Http\Middleware\LoginThrottle;
use Beliven\Prova01\Listeners\RecordFailedLoginAttempt;
use Beliven\Prova01\Listeners\ResetLoginAttemptsOnSuccess;

class Prova01ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name("prova-01")
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration("create_prova_01_table")
            ->hasCommand(Prova01Command::class);
    }

    /**
     * Bootstrap any package services.
     *
     * We register:
     * - a route middleware alias: 'prova01.login.throttle'
     * - event listeners for failed and successful logins
     */
    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerEventListeners();
    }

    protected function registerMiddleware(): void
    {
        // Register a route middleware alias so consuming apps can use:
        // ->middleware('prova01.login.throttle')
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware(
            "prova01.login.throttle",
            LoginThrottle::class,
        );
    }

    protected function registerEventListeners(): void
    {
        // Listen for failed and successful login events to manage attempt counters.
        Event::listen(Failed::class, RecordFailedLoginAttempt::class);
        Event::listen(Login::class, ResetLoginAttemptsOnSuccess::class);
    }
}
