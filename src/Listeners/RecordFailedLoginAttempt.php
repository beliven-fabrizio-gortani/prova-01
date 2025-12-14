<?php

namespace Beliven\Prova01\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Listener that records failed login attempts and sets a lockout when a threshold is reached.
 *
 * Behavior:
 * - Uses cache keys:
 *     - attempts key: prova01:login_attempts:{identifier}
 *     - lockout key:  prova01:login_lockout:{identifier}
 * - On a failed login it increments (or creates) the attempts counter with an expiration of `decay_minutes`.
 * - When the attempts counter reaches `max_attempts` it writes a lockout key containing the unix
 *   expiry timestamp and sets its TTL to `lockout_duration` minutes.
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
    protected string $attemptsPrefix = 'prova01:login_attempts:';
    protected string $lockoutPrefix = 'prova01:login_lockout:';

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Auth\Events\Failed  $event
     * @return void
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

        $attemptsKey = $this->attemptsPrefix . $identifier;
        $lockoutKey = $this->lockoutPrefix . $identifier;

        // If already locked, nothing to do.
        if (Cache::has($lockoutKey)) {
            return;
        }

        // Increment attempts with a TTL (decay). Different cache drivers behave differently for increment,
        // so handle creation and increment robustly.
        if (! Cache::has($attemptsKey)) {
            // Create the key with value 1 and set expiry (in seconds)
            Cache::put($attemptsKey, 1, $decayMinutes * 60);
            $attempts = 1;
        } else {
            // Prefer atomic increment when available
            $attempts = Cache::increment($attemptsKey);
            // Some drivers may return false; fall back to manual increment
            if ($attempts === false || $attempts === null) {
                $attempts = (int) Cache::get($attemptsKey, 0) + 1;
                Cache::put($attemptsKey, $attempts, $decayMinutes * 60);
            }
        }

        // If threshold reached or exceeded, set lockout key with expiry timestamp
        if ($attempts >= $maxAttempts) {
            $expiresAt = time() + ($lockoutDuration * 60);
            // Store expiry timestamp as the value (use TTL to auto-expire)
            Cache::put($lockoutKey, $expiresAt, $lockoutDuration * 60);

            // Optionally, reset attempts counter so repeated lock doesn't accumulate
            Cache::forget($attemptsKey);
        }
    }

    /**
     * Resolve an identifier string from the Failed event.
     *
     * @param Failed $event
     * @return string
     */
    protected function resolveIdentifierFromFailedEvent(Failed $event): string
    {
        $credentials = $event->credentials ?? [];

        if (is_array($credentials)) {
            if (! empty($credentials['email'])) {
                return 'email|' . Str::lower((string) $credentials['email']);
            }

            if (! empty($credentials['username'])) {
                return 'username|' . Str::lower((string) $credentials['username']);
            }
        }

        // As a last resort use request IP if available; when running in contexts without a request,
        // fall back to a generic identifier so attempts are still tracked (but grouped).
        try {
            $ip = request()->ip();
            if ($ip) {
                return 'ip|' . $ip;
            }
        } catch (\Throwable $e) {
            // ignore - no request available
        }

        return 'unknown|global';
    }
}
