<?php

namespace Beliven\Lockout;

use Beliven\Lockout\Models\Lockout as LockoutModel;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Main Lockout service.
 *
 * Responsibilities:
 * - Track failed attempts for an identifier (email/username) in persistent storage (DB).
 * - Lock an identifier when configured max attempts is reached (persistent lock by default).
 * - Provide helper methods to check lock state, unlock, and reset attempts.
 *
 * This service intentionally uses the database-backed `Lockout` model so lock state is
 * persistent and not stored in cache. Events are dispatched on failed attempt, locked
 * and unlocked actions to let the host application react (notifications, audits, etc).
 */
class Lockout
{
    protected LockoutModel $model;
    protected Dispatcher $events;

    public function __construct(Dispatcher $events)
    {
        $this->model =
            Config::get("lockout.model", LockoutModel::class) ===
            LockoutModel::class
                ? new LockoutModel()
                : app(Config::get("lockout.model"));
        $this->events = $events;
    }

    /**
     * Record a failed authentication attempt for the given identifier.
     *
     * @param string $identifier  The login identifier (email, username, ...)
     * @param int|null $userId    Optional user id if known
     * @param array $metadata     Optional metadata (ip, user_agent, etc)
     * @return LockoutModel
     */
    public function recordFailedAttempt(
        string $identifier,
        ?int $userId = null,
        array $metadata = [],
    ): LockoutModel {
        $record = LockoutModel::forIdentifier($identifier, $userId);

        // If already locked, do not increment but still return record
        if ($record->isLocked()) {
            return $record;
        }

        $record->incrementAttempts(1);

        // attach metadata (merge)
        if (!empty($metadata)) {
            $existing = (array) $record->metadata;
            $record->metadata = array_merge($existing, $metadata);
            $record->save();
        }

        // fire failed attempt event
        $this->dispatchEvent(
            "attempt_failed",
            new Events\FailedAttempt($record),
        );

        // If attempts exceed configured max, lock the account
        $max = Config::get("lockout.max_attempts", 5);
        if ($record->attempts >= $max) {
            $record->lock("too_many_attempts", ["max" => $max]);
            $this->dispatchEvent("locked", new Events\UserLocked($record));
        }

        return $record;
    }

    /**
     * Check if an identifier is locked.
     *
     * @param string $identifier
     * @return bool
     */
    public function isLocked(string $identifier): bool
    {
        $record = LockoutModel::where("identifier", $identifier)->first();

        return $record ? $record->isLocked() : false;
    }

    /**
     * Lock an identifier explicitly.
     *
     * @param string $identifier
     * @param int|null $userId
     * @param string|null $reason
     * @param array|null $metadata
     * @return LockoutModel
     */
    public function lock(
        string $identifier,
        ?int $userId = null,
        ?string $reason = null,
        ?array $metadata = null,
    ): LockoutModel {
        $record = LockoutModel::forIdentifier($identifier, $userId);
        if (!$record->isLocked()) {
            $record->lock($reason, $metadata);
            $this->dispatchEvent("locked", new Events\UserLocked($record));
        }

        return $record;
    }

    /**
     * Unlock an identifier.
     *
     * @param string $identifier
     * @param bool $resetAttempts
     * @return LockoutModel|null
     */
    public function unlock(
        string $identifier,
        bool $resetAttempts = true,
    ): ?LockoutModel {
        $record = LockoutModel::where("identifier", $identifier)->first();

        if (!$record) {
            return null;
        }

        if ($record->isLocked()) {
            $record->unlock($resetAttempts);
            $this->dispatchEvent("unlocked", new Events\UserUnlocked($record));
        }

        return $record;
    }

    /**
     * Get attempts count for identifier (0 if none).
     *
     * @param string $identifier
     * @return int
     */
    public function getAttempts(string $identifier): int
    {
        $record = LockoutModel::where("identifier", $identifier)->first();

        return $record ? $record->attempts ?? 0 : 0;
    }

    /**
     * Reset attempts to zero for identifier.
     *
     * @param string $identifier
     * @return LockoutModel|null
     */
    public function resetAttempts(string $identifier): ?LockoutModel
    {
        $record = LockoutModel::where("identifier", $identifier)->first();

        if (!$record) {
            return null;
        }

        $record->resetAttempts();
        return $record;
    }

    /**
     * Helper to dispatch events by alias configured in config.lockout.events or default.
     *
     * @param string $alias  'attempt_failed'|'locked'|'unlocked'
     * @param object $event
     * @return void
     */
    protected function dispatchEvent(string $alias, object $event): void
    {
        // Dispatch the event both by the concrete class and by configuration alias
        try {
            $this->events->dispatch($event);
        } catch (\Throwable $e) {
            // swallow to avoid breaking authentication flow if events fail
        }

        // Also dispatch an event name from config if present (legacy)
        $eventClass = Config::get("lockout.events." . $alias, null);
        if ($eventClass && is_string($eventClass)) {
            try {
                $this->events->dispatch(
                    new $eventClass(
                        $event instanceof LockoutModel ? $event : null,
                    ),
                );
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Minimal middleware and event classes
|--------------------------------------------------------------------------
|
| For convenience the package provides a simple middleware which can be
| registered by consumers. These classes are intentionally minimal here
| — applications can override events or implement more advanced listeners.
|
*/

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
 */
class LockoutCheckMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $identifierKey = Config::get("lockout.identifier_column", "email");

        $identifier =
            $request->input($identifierKey) ?? $request->get($identifierKey);

        // If no identifier present, continue (nothing to check)
        if (empty($identifier)) {
            return $next($request);
        }

        /** @var \Beliven\Lockout\Lockout $service */
        $service = App::make(\Beliven\Lockout\Lockout::class);

        if ($service->isLocked((string) $identifier)) {
            $message = Config::get("lockout.message", "Account locked.");
            // 423 Locked (WebDAV) is commonly used to indicate resource locked
            throw new HttpException(423, $message);
        }

        return $next($request);
    }
}

namespace Beliven\Lockout\Events;

use Beliven\Lockout\Models\Lockout as LockoutModel;

/**
 * Dispatched each time a failed attempt is recorded.
 */
class FailedAttempt
{
    public LockoutModel $lockout;

    public function __construct(LockoutModel $lockout)
    {
        $this->lockout = $lockout;
    }
}

/**
 * Dispatched when a user/identifier is locked.
 */
class UserLocked
{
    public LockoutModel $lockout;

    public function __construct(LockoutModel $lockout)
    {
        $this->lockout = $lockout;
    }
}

/**
 * Dispatched when a user/identifier is unlocked.
 */
class UserUnlocked
{
    public LockoutModel $lockout;

    public function __construct(LockoutModel $lockout)
    {
        $this->lockout = $lockout;
    }
}
