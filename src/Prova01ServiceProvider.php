<?php

namespace Beliven\Prova01;

use Beliven\Prova01\Commands\Prova01Command;
use Beliven\Prova01\Http\Middleware\LoginThrottle;
use Beliven\Prova01\Listeners\RecordFailedLoginAttempt;
use Beliven\Prova01\Listeners\ResetLoginAttemptsOnSuccess;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
            ->hasMigration("create_login_locks_table");
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

        // Register publishable resources so `php artisan vendor:publish` works.
        // Migration stub (published with a timestamp prefix)
        $migrationStub =
            __DIR__ .
            "/../database/migrations/create_login_locks_table.php.stub";
        if (is_file($migrationStub)) {
            $this->publishes(
                [
                    $migrationStub => database_path(
                        "migrations/" .
                            date("Y_m_d_His") .
                            "_create_login_locks_table.php",
                    ),
                ],
                "prova-01-migrations",
            );
        }

        // Configuration file
        $configStub = __DIR__ . "/../config/prova-01.php";
        if (is_file($configStub)) {
            $this->publishes(
                [
                    $configStub => config_path("prova-01.php"),
                ],
                "prova-01-config",
            );
        }

        // Views (optional)
        $viewsDir = __DIR__ . "/../resources/views";
        if (is_dir($viewsDir)) {
            $this->publishes(
                [
                    $viewsDir => resource_path("views/vendor/prova-01"),
                ],
                "prova-01-views",
            );
        }
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
