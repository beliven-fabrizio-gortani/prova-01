<?php

// config for Beliven/Prova01
return [
    /*
    |--------------------------------------------------------------------------
    | Login Throttle (simple demo)
    |--------------------------------------------------------------------------
    |
    | This package provides a very small and opinionated login-throttle
    | configuration for demo purposes.
    |
    | - max_attempts: number of failed attempts before locking the user
    | - decay_minutes: minutes after which the attempts counter resets
    | - lockout_duration: minutes the user remains locked out after exceeding attempts
    | - lockout_message: message returned to the user when locked out
    |
    */
    'login_throttle' => [
        'max_attempts' => 5,
        'decay_minutes' => 1,
        'lockout_duration' => 15,
        'lockout_message' => 'Too many login attempts. Please try again later.',
    ],
];
