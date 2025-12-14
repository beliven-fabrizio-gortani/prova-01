<?php

namespace Beliven\Prova01\Http\Middleware;

use Beliven\Prova01\Models\LoginLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Middleware that checks persistent login locks stored in the database.
 *
 * This version reads the `login_locks` table (via the LoginLock model) to decide
 * whether the current identifier is locked. The increment/reset of attempts is
 * performed by event listeners; the middleware only blocks requests while locked.
 */
class LoginThrottle
{
    /**
     * Handle an incoming request.
     *
     * If the identifier is locked, returns a 429 JSON response with the configured
     * lockout message and a Retry-After header (seconds until unlock).
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $config = Config::get('prova-01.login_throttle', [
            'max_attempts' => 5,
            'decay_minutes' => 1,
            'lockout_duration' => 15,
            'lockout_message' => 'Too many login attempts. Please try again later.',
        ]);

        $identifier = $this->getIdentifier($request);

        // Use persistent DB-backed model to determine lock state
        try {
            $lock = LoginLock::findOrCreate($identifier);
        } catch (\Throwable $e) {
            // If DB is not available for some reason, allow the request to proceed to avoid
            // locking out users due to infra issues. In production you might want to log this.
            return $next($request);
        }

        if ($lock->isLocked()) {
            $secondsLeft = $lock->secondsUntilUnlock();
            $message =
                $config['lockout_message'] ??
                'Too many login attempts. Please try again later.';

            $response = response()->json(
                [
                    'message' => $message,
                    'retry_after' => $secondsLeft,
                ],
                429,
            );

            // Add Retry-After header (in seconds) if we know the remaining time
            if ($secondsLeft !== null) {
                $response->headers->set(
                    'Retry-After',
                    (string) max(0, (int) $secondsLeft),
                );
            }

            return $response;
        }

        return $next($request);
    }

    /**
     * Resolve an identifier for throttling.
     *
     * Default behavior:
     * - If the request contains 'email' use that.
     * - Else if contains 'username' use that.
     * - Else fallback to client IP.
     */
    protected function getIdentifier(Request $request): string
    {
        if ($request->filled('email')) {
            return 'email|'.mb_strtolower((string) $request->input('email'));
        }

        if ($request->filled('username')) {
            return 'username|'.
                mb_strtolower((string) $request->input('username'));
        }

        // fallback to IP
        return 'ip|'.$request->ip();
    }
}
