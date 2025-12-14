<?php

// config for Beliven/Lockout
return [
    /*
    |--------------------------------------------------------------------------
    | Lockout table
    |--------------------------------------------------------------------------
    |
    | The database table name where lockout records will be stored.
    |
    */
    'table' => env('LOCKOUT_TABLE', 'lockouts'),

    /*
    |--------------------------------------------------------------------------
    | Lockout model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to interact with the lockouts table. You can
    | override this if you want to extend the model in your application.
    |
    */
    'model' => Beliven\Lockout\Models\Lockout::class,

    /*
    |--------------------------------------------------------------------------
    | Maximum failed attempts
    |--------------------------------------------------------------------------
    |
    | Number of failed authentication attempts after which a user will be
    | considered locked. This package persists lockouts in the database and
    | does not rely on cache by default.
    |
    */
    'max_attempts' => env('LOCKOUT_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Expiration behavior
    |--------------------------------------------------------------------------
    |
    | By default lockouts are persistent and do not expire (null). If you want
    | automatic expiration, set this to an integer number of seconds.
    |
    */
    'expires_after_seconds' => env('LOCKOUT_EXPIRES_AFTER_SECONDS', null),

    /*
    |--------------------------------------------------------------------------
    | Use cache for attempts
    |--------------------------------------------------------------------------
    |
    | This package stores final lockout state in database (persistent). Setting
    | this to true will still use cache to track intermediate attempt counters,
    | but by default we keep it false to satisfy "no cache" requirement.
    |
    */
    'use_cache' => env('LOCKOUT_USE_CACHE', false),

    /*
    |--------------------------------------------------------------------------
    | Middleware configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for middleware that prevents login (or other actions) for
    | locked users. The package registers a middleware alias; you may change
    | the alias name here if needed.
    |
    */
    'middleware' => [
        'alias' => 'lockout.check',
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Events dispatched by the package. You can listen to these in your app to
    | run side effects (notifications, audits, etc).
    |
    */
    'events' => [
        'locked' => Beliven\Lockout\Events\UserLocked::class,
        'unlocked' => Beliven\Lockout\Events\UserUnlocked::class,
        'attempt_failed' => Beliven\Lockout\Events\FailedAttempt::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Service class
    |--------------------------------------------------------------------------
    |
    | The service class that contains the main lockout logic. You can provide
    | your own implementation that follows the same public API and bind it in
    | the service container.
    |
    */
    'service' => Beliven\Lockout\Lockout::class,

    /*
    |--------------------------------------------------------------------------
    | Migration name
    |--------------------------------------------------------------------------
    |
    | When the package publishes/loads migrations, it will look for the stub
    | with this name. The package skeleton already contains a matching stub.
    |
    */
    'migration' => 'create_lockout_table',
];
