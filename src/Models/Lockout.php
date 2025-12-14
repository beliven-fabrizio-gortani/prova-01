<?php

namespace Beliven\Lockout\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistent lockout record
 *
 * Fields:
 * - id
 * - user_id (nullable)
 * - identifier (e.g. email or username)
 * - attempts (int)
 * - locked_at (datetime, nullable)
 * - reason (nullable string)
 * - metadata (json, nullable)
 * - created_at, updated_at
 */
class Lockout extends Model
{
    /**
     * The table name is resolved from configuration to allow customization.
     *
     * We set it in the constructor so config() is available when package is used.
     *
     * @var string
     */
    protected $table;

    /**
     * Mass assignable attributes.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'user_id',
        'identifier',
        'attempts',
        'locked_at',
        'reason',
        'metadata',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'attempts' => 'integer',
        'locked_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Create a new model instance and set the table name from config.
     */
    public function __construct(array $attributes = [])
    {
        $this->table = config('lockout.table', 'lockouts');

        parent::__construct($attributes);
    }

    /**
     * Relation to the user model (if available).
     */
    public function user(): BelongsTo
    {
        $userModel = config('lockout.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Mark this lockout as locked now.
     *
     * @return $this
     */
    public function lock(?string $reason = null, ?array $metadata = null): self
    {
        $this->locked_at = Carbon::now();
        if ($reason !== null) {
            $this->reason = $reason;
        }
        if ($metadata !== null) {
            $this->metadata = $metadata;
        }
        $this->save();

        // Fire event if you want (left to application / service to dispatch)

        return $this;
    }

    /**
     * Unlock this record (clear locked_at and reset attempts if desired).
     *
     * @return $this
     */
    public function unlock(bool $resetAttempts = true): self
    {
        $this->locked_at = null;

        if ($resetAttempts) {
            $this->attempts = 0;
        }

        $this->save();

        return $this;
    }

    /**
     * Increment attempts and return the model.
     *
     * @return $this
     */
    public function incrementAttempts(int $by = 1): self
    {
        $this->attempts = ($this->attempts ?? 0) + $by;
        $this->save();

        return $this;
    }

    /**
     * Reset attempts to zero.
     *
     * @return $this
     */
    public function resetAttempts(): self
    {
        $this->attempts = 0;
        $this->save();

        return $this;
    }

    /**
     * Determine if this record is locked.
     *
     * By default this checks for non-null locked_at. Expiration behavior (if any)
     * can be implemented by the service layer — config('lockout.expires_after_seconds')
     * is available for optional expiry semantics.
     */
    public function isLocked(): bool
    {
        if ($this->locked_at === null) {
            return false;
        }

        $expires = config('lockout.expires_after_seconds', null);

        if ($expires === null) {
            // Persistent lock (no automatic expiry)
            return true;
        }

        return $this->locked_at->diffInSeconds(Carbon::now()) < $expires;
    }

    /**
     * Find or create a lockout record for the given identifier.
     *
     * @return static
     */
    public static function forIdentifier(string $identifier, ?int $userId = null): self
    {
        $table = config('lockout.table', 'lockouts');

        /** @var static|null $record */
        $record = static::where('identifier', $identifier)->first();

        if (! $record) {
            $record = static::create([
                'user_id' => $userId,
                'identifier' => $identifier,
                'attempts' => 0,
                'locked_at' => null,
                'reason' => null,
                'metadata' => null,
            ]);
        } else {
            // ensure user_id is stored if provided and missing
            if ($userId !== null && ($record->user_id === null || $record->user_id !== $userId)) {
                $record->user_id = $userId;
                $record->save();
            }
        }

        return $record;
    }

    /**
     * Helper: lock if attempts reached configured max attempts.
     *
     * @return bool true if newly locked
     */
    public function lockIfExceeded(?int $maxAttempts = null, ?string $reason = null, ?array $metadata = null): bool
    {
        $max = $maxAttempts ?? config('lockout.max_attempts', 5);

        if ($this->attempts >= $max && ! $this->isLocked()) {
            $this->lock($reason, $metadata);

            return true;
        }

        return false;
    }
}
