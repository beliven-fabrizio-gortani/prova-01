<?php

namespace Beliven\Lockout\Events;

use Beliven\Lockout\Models\Lockout;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a user (or identifier) becomes locked due to too many
 * failed authentication attempts. Listeners can use this to notify the user,
 * log the event, or trigger additional protection measures.
 */
class UserLocked
{
    use Dispatchable, SerializesModels;

    /**
     * The lockout model instance representing the locked identifier.
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
