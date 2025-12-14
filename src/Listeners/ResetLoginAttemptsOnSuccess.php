<?php

namespace Beliven\Prova01\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Listener that resets login attempts and removes any lockout when a user successfully logs in.
 *
 * Behavior:
 * - Removes cache keys used by the demo throttle:
 *     - attempts key: prova01:login_attempts:{identifier}
 *     - lockout key:  prova01:login_lockout:{identifier}
 *
 * Identifier resolution:
 * - Prefers the logged-in user's `email` attribute (lowercased)
 * - Falls back to `username` attribute if present
 * - Finally falls back to the current request IP
 *
 * This keeps the demo behaviour consistent with the failed-attempt listener.
 */
class ResetLoginAttemptsOnSuccess
{
    protected string $attemptsPrefix = 'prova01:login_attempts:';
    protected string $lockoutPrefix = 'prova01:login_lockout:';

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Auth\Events\Login  $event
     * @return void
     */
    public function handle(Login $event): void
    {
        $identifier = $this->resolveIdentifierFromLoginEvent($event);

        $attemptsKey = $this->attemptsPrefix . $identifier;
        $lockoutKey = $this->lockoutPrefix . $identifier;

        // Remove attempts counter and any existing lockout for this identifier.
        Cache::forget($attemptsKey);
        Cache::forget($lockoutKey);
    }

    /**
     * Resolve an identifier string from the Login event.
     *
     * @param  \Illuminate\Auth\Events\Login  $event
     * @return string
     */
    protected function resolveIdentifierFromLoginEvent(Login $event): string
    {
        $user = $event->user;

        if ($user !== null) {
            // Prefer email
            if (isset($user->email) && $user->email) {
                return 'email|' . Str::lower((string) $user->email);
            }

            // Prefer username property if present
            if (isset($user->username) && $user->username) {
                return 'username|' . Str::lower((string) $user->username);
            }

            // Some apps may use different identifiers, attempt id as fallback (grouped)
            if (isset($user->id)) {
                return 'id|' . (string) $user->id;
            }
        }

        // Fallback to current request IP when available
        try {
            $ip = request()->ip();
            if ($ip) {
                return 'ip|' . $ip;
            }
        } catch (\Throwable $e) {
            // ignore - no request available
        }

        // Final fallback to a global identifier to avoid exceptions
        return 'unknown|global';
    }
}
