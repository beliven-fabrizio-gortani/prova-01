<?php

namespace Beliven\Prova01\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * LoginLock model
 *
 * Manages persistent tracking of failed login attempts and lockout windows.
 *
 * Table: login_locks
 * Columns:
 *  - id
 *  - identifier (string, unique) e.g. "email|user@example.com" or "ip|127.0.0.1"
 *  - attempts (unsigned integer)
 *  - locked_until (timestamp, nullable)
 *  - created_at, updated_at
 */
class LoginLock extends Model
{
    use HasFactory;

    protected $table = 'login_locks';

    protected $fillable = [
        'identifier',
        'attempts',
        'locked_until',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'locked_until' => 'datetime',
    ];

    /**
     * Scope a query to the given identifier.
     */
    public function scopeForIdentifier(Builder $query, string $identifier): Builder
    {
        return $query->where('identifier', $identifier);
    }

    /**
     * Find an entry by identifier or create a new one with default values.
     */
    public static function findOrCreate(string $identifier): self
    {
        return static::firstOrCreate(
            ['identifier' => $identifier],
            ['attempts' => 0, 'locked_until' => null]
        );
    }

    /**
     * Increment attempts and apply lockout if threshold is reached.
     *
     * Behavior (opinionated for demo):
     * - increments the attempts counter and persists it;
     * - if attempts >= $maxAttempts, sets locked_until to now + $lockoutDurationMinutes
     *   and resets attempts to 0 (so repeated lockouts are counted from scratch).
     *
     * Returns true if the record became locked as a result of this call.
     *
     * @param  int  $decayMinutes  // not directly used here but left for symmetry with cache impl
     */
    public function incrementAttempts(int $maxAttempts = 5, int $decayMinutes = 1, int $lockoutDurationMinutes = 15): bool
    {
        // If currently locked, do nothing.
        if ($this->isLocked()) {
            return true;
        }

        $this->attempts = ($this->attempts ?? 0) + 1;
        $this->save();

        if ($this->attempts >= $maxAttempts) {
            $this->locked_until = Carbon::now()->addMinutes($lockoutDurationMinutes);
            // Reset attempts after locking so additional failed attempts during lockout don't extend counters
            $this->attempts = 0;
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Reset attempts and remove any lockout.
     */
    public function resetAttempts(): void
    {
        $this->attempts = 0;
        $this->locked_until = null;
        $this->save();
    }

    /**
     * Force a lock until a given number of minutes in the future.
     */
    public function lockForMinutes(int $minutes): void
    {
        $this->locked_until = Carbon::now()->addMinutes($minutes);
        $this->attempts = 0;
        $this->save();
    }

    /**
     * Determine if the identifier is currently locked.
     */
    public function isLocked(): bool
    {
        if (! $this->locked_until) {
            return false;
        }

        return $this->locked_until->isFuture();
    }

    /**
     * Get seconds remaining until unlock, or null if not locked.
     */
    public function secondsUntilUnlock(): ?int
    {
        if (! $this->isLocked()) {
            return null;
        }

        return max(0, $this->locked_until->getTimestamp() - Carbon::now()->getTimestamp());
    }
}
