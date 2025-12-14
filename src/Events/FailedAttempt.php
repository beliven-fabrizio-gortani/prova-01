<?php

namespace Beliven\Lockout\Events;

use Beliven\Lockout\Models\Lockout;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a failed authentication attempt is recorded
 * for a given identifier (email/username). Contains the persistent
 * Lockout model instance representing the current state for that identifier.
 */
class FailedAttempt
{
    use Dispatchable, SerializesModels;

    /**
     * The lockout model instance.
     *
     * @var \Beliven\Lockout\Models\Lockout
     */
    public Lockout $lockout;

    /**
     * Create a new event instance.
     *
     * @param  \Beliven\Lockout\Models\Lockout  $lockout
     * @return void
     */
    public function __construct(Lockout $lockout)
    {
        $this->lockout = $lockout;
    }
}
