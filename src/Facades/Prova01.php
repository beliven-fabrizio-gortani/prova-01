<?php

namespace Beliven\Prova01\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Beliven\Prova01\Prova01
 */
class Prova01 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Beliven\Prova01\Prova01::class;
    }
}
