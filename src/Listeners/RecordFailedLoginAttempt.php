<?php

namespace Beliven\Prova01\Listeners;

use Beliven\Prova01\Models\LoginLock;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Listener that records failed login attempts and sets a persistent DB lockout when a threshold is reached.
 *
 * Behavior:
 * - Uses the `login_locks` table (via the LoginLock model) to persist attempts and lockout windows.
 * - On a failed login it increments the attempts counter and, when threshold reached, sets `locked_until`.
 *
 * Identifier resolution:
 * - Prefers `email` from the event credentials (lowercased)
 * - Falls back to `username` from the credentials
 * - Finally falls back to the current request IP (if available)
 *
 * This listener is intentionally simple and opinionated for demo purposes.
 */
class RecordFailedLoginAttempt
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $config = Config::get('prova-01.login_throttle', [
            'max_attempts' => 5,
            'decay_minutes' => 1,
            'lockout_duration' => 15,
            'lockout_message' => 'Too many login attempts. Please try again later.',
        ]);

        $maxAttempts = (int) ($config['max_attempts'] ?? 5);
        $decayMinutes = (int) ($config['decay_minutes'] ?? 1);
        $lockoutDuration = (int) ($config['lockout_duration'] ?? 15);

        $identifier = $this->resolveIdentifierFromFailedEvent($event);

        // Use persistent DB-backed model to track attempts/locks
        $lock = LoginLock::findOrCreate($identifier);

        // If already locked, nothing to do.
        if ($lock->isLocked()) {
            return;
        }

        // Increment attempts; model will set locked_until and reset attempts when threshold reached.
        $becameLocked = $lock->incrementAttempts(
            $maxAttempts,
            $decayMinutes,
            $lockoutDuration,
        );

        // Nothing else to do here. The middleware reads the DB model to decide whether to block.
    }

    /**
     * Resolve an identifier string from the Failed event.
     */
    protected function resolveIdentifierFromFailedEvent(Failed $event): string
    {
        $credentials = $event->credentials ?? [];

        if (is_array($credentials)) {
            if (! empty($credentials['email'])) {
                return 'email|'.Str::lower((string) $credentials['email']);
            }

            if (! empty($credentials['username'])) {
                return 'username|'.
                    Str::lower((string) $credentials['username']);
            }
        }

        // As a last resort use request IP if available; when running in contexts without a request,
        // fall back to a generic identifier so attempts are still tracked (but grouped).
        try {
            $ip = request()->ip();
            if ($ip) {
                return 'ip|'.$ip;
            }
        } catch (\Throwable $e) {
            // ignore - no request available
        }

        return 'unknown|global';
    }
}
