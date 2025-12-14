<?php

namespace Beliven\Lockout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Middleware that checks whether the login identifier in the request is locked.
 *
 * Typical usage: apply to the login route to block requests for locked accounts.
 *
 * - Reads the identifier key from config('lockout.identifier_column', 'email')
 * - Uses the Lockout service bound in the container to determine lock state
 * - Throws an HttpException(423) when the identifier is locked
 */
class LockoutCheckMiddleware
{
    /**
     * Handle an incoming request.
     *
     * If the request contains the configured identifier (e.g. email) and that
     * identifier is locked according to the persistent lockout store, this
     * middleware will abort the request with a 423 Locked response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle(Request $request, Closure $next)
    {
        $identifierKey = Config::get('lockout.identifier_column', 'email');

        // Try common places for the identifier: POST input, query string, route parameter
        $identifier = $request->input($identifierKey);

        if (empty($identifier)) {
            // Check route parameters as fallback
            $identifier = $request->route($identifierKey) ?? $request->query($identifierKey);
        }

        // Nothing to check if identifier not provided
        if (empty($identifier)) {
            return $next($request);
        }

        // Resolve the Lockout service from the container. Consumer apps may bind their own implementation.
        $service = App::make(\Beliven\Lockout\Lockout::class);

        // If the service can't be resolved for some reason, allow the request to continue
        if (! $service) {
            return $next($request);
        }

        // If locked, abort with 423 Locked and configurable message
        if ($service->isLocked((string) $identifier)) {
            $message = Config::get('lockout.message', 'Your account has been locked due to too many failed login attempts.');
            throw new HttpException(423, $message);
        }

        return $next($request);
    }
}
