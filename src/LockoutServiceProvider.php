<?php

namespace Beliven\Lockout;

use Beliven\Lockout\Commands\LockoutCommand;
use Beliven\Lockout\Http\Middleware\LockoutCheckMiddleware;
use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for the Lockout package.
 *
 * Responsibilities added:
 * - Configure package assets (config, views, migrations, commands).
 * - Register the `Lockout` service in the container.
 * - Register a middleware alias (configurable) that checks for locked identifiers.
 */
class LockoutServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('lockout')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_lockout_table')
            ->hasCommand(LockoutCommand::class);
    }

    /**
     * Register container bindings.
     */
    public function register(): void
    {
        parent::register();

        // Bind the main Lockout service as a singleton so consumers can resolve it
        $this->app->singleton(Lockout::class, function ($app) {
            return new Lockout($app['events']);
        });

        // Provide a short alias to resolve the service via the container if needed
        $this->app->alias(Lockout::class, 'lockout');
    }

    /**
     * Boot services (register middleware alias).
     */
    public function boot(): void
    {
        parent::boot();

        // Register middleware alias using configured alias or a sensible default.
        // We resolve the router lazily so this provider remains test-friendly.
        $alias = config('lockout.middleware.alias', 'lockout.check');

        if ($this->app->bound(Router::class)) {
            $router = $this->app->make(Router::class);
            $router->aliasMiddleware($alias, LockoutCheckMiddleware::class);
        }
    }
}
