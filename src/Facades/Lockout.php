<?php

namespace Beliven\Lockout\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Beliven\Lockout\Lockout
 */
class Lockout extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Beliven\Lockout\Lockout::class;
    }
}
