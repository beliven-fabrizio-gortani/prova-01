<?php

namespace Beliven\Lockout\Events;

use Beliven\Lockout\Models\Lockout;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a user (or identifier) is unlocked.
 *
 * Listeners can use this to notify the user, record an audit entry, or
 * perform other post-unlock actions.
 */
class UserUnlocked
{
    use Dispatchable, SerializesModels;

    /**
     * The lockout model instance representing the unlocked identifier.
     */
    public Lockout $lockout;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Lockout $lockout)
    {
        $this->lockout = $lockout;
    }
}
