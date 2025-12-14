<?php

namespace Beliven\Prova01\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Simple login throttle middleware for demo purposes.
 *
 * Usage:
 * - Register middleware alias (already done in the package service provider)
 * - Apply to your login route, e.g. `->middleware('prova01.login.throttle')`
 *
 * Behavior:
 * - The middleware checks if the current login identifier (email, username or client IP)
 *   is currently locked out and, if so, returns a 429 response with a Retry-After header.
 * - The middleware does NOT increment the failed attempt counter; that is expected to be
 *   handled by listeners attached to authentication events (the package provides such listeners).
 *
 * Implementation notes:
 * - Attempts and lockout information are read from cache keys:
 *     - attempts key:   `prova01:login_attempts:{identifier}`
 *     - lockout key:    `prova01:login_lockout:{identifier}`
 * - The lockout key is expected to store an integer timestamp (UNIX seconds) representing
 *   when the lockout expires. If a different shape is used, the middleware will try to
 *   interpret it sensibly (if boolean true it will treat as an indefinite lockout).
 */
class LoginThrottle
{
    protected string $attemptsPrefix = 'prova01:login_attempts:';

    protected string $lockoutPrefix = 'prova01:login_lockout:';

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

        $lockoutKey = $this->lockoutPrefix.$identifier;

        if ($this->isLocked($lockoutKey)) {
            [$secondsLeft, $message] = $this->getLockoutInfo($lockoutKey, $config);

            $response = response()->json([
                'message' => $message,
                'retry_after' => $secondsLeft,
            ], 429);

            // Add Retry-After header (in seconds)
            if ($secondsLeft !== null) {
                $response->headers->set('Retry-After', (string) max(0, (int) $secondsLeft));
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
     *
     * This keeps the demo simple and works for typical auth forms.
     */
    protected function getIdentifier(Request $request): string
    {
        if ($request->filled('email')) {
            return 'email|'.mb_strtolower((string) $request->input('email'));
        }

        if ($request->filled('username')) {
            return 'username|'.mb_strtolower((string) $request->input('username'));
        }

        // fallback to IP
        return 'ip|'.$request->ip();
    }

    /**
     * Determine whether a lockout key indicates a current lockout.
     *
     * The lockout data is expected to be a UNIX timestamp (expiry time).
     * If the stored value is true/1 we treat it as locked but with unknown expiry.
     */
    protected function isLocked(string $lockoutKey): bool
    {
        if (! Cache::has($lockoutKey)) {
            return false;
        }

        $value = Cache::get($lockoutKey);

        // If value is numeric, compare to now
        if (is_numeric($value)) {
            return ((int) $value) > time();
        }

        // Any non-empty value is treated as locked (defensive)
        return (bool) $value;
    }

    /**
     * Compute seconds left for lockout and the message to show.
     *
     * @return array [int|null $secondsLeft, string $message]
     */
    protected function getLockoutInfo(string $lockoutKey, array $config): array
    {
        $raw = Cache::get($lockoutKey);

        $message = $config['lockout_message'] ?? 'Too many login attempts. Please try again later.';

        if (is_numeric($raw)) {
            $expiresAt = (int) $raw;
            $secondsLeft = max(0, $expiresAt - time());

            return [$secondsLeft, $message];
        }

        // Unknown expiry: return null for seconds (no header), keep message
        return [null, $message];
    }
}
