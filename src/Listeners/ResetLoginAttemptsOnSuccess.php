<?php

namespace Beliven\Prova01\Listeners;

use Beliven\Prova01\Models\LoginLock;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

/**
 * Listener that resets login attempts and removes any persistent lockout when a user successfully logs in.
 *
 * Behavior:
 * - Uses the `login_locks` table (via the LoginLock model) to clear attempts and any lockout for the identifier.
 *
 * Identifier resolution:
 * - Prefers the logged-in user's `email` attribute (lowercased)
 * - Falls back to `username` attribute if present
 * - Finally falls back to the current request IP
 */
class ResetLoginAttemptsOnSuccess
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $identifier = $this->resolveIdentifierFromLoginEvent($event);

        // Find the persistent lock record and reset attempts / remove lockout if present.
        try {
            $lock = LoginLock::findOrCreate($identifier);
            $lock->resetAttempts();
        } catch (\Throwable $e) {
            // In case the DB is not available or other problem, fail silently for demo purposes.
            // In production you might want to log this.
        }
    }

    /**
     * Resolve an identifier string from the Login event.
     */
    protected function resolveIdentifierFromLoginEvent(Login $event): string
    {
        $user = $event->user;

        if ($user !== null) {
            // Prefer email
            if (isset($user->email) && $user->email) {
                return 'email|'.Str::lower((string) $user->email);
            }

            // Prefer username property if present
            if (isset($user->username) && $user->username) {
                return 'username|'.Str::lower((string) $user->username);
            }

            // Some apps may use different identifiers, attempt id as fallback (grouped)
            if (isset($user->id)) {
                return 'id|'.(string) $user->id;
            }
        }

        // Fallback to current request IP when available
        try {
            $ip = request()->ip();
            if ($ip) {
                return 'ip|'.$ip;
            }
        } catch (\Throwable $e) {
            // ignore - no request available
        }

        // Final fallback to a global identifier to avoid exceptions
        return 'unknown|global';
    }
}
