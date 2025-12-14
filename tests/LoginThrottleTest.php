<?php

namespace Beliven\Prova01\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

it(
    "locks an identifier after configured failed attempts and middleware blocks further requests",
    function () {
        // Use a small threshold for fast tests
        Config::set("prova-01.login_throttle.max_attempts", 3);
        Config::set("prova-01.login_throttle.decay_minutes", 1);
        Config::set("prova-01.login_throttle.lockout_duration", 1);
        Config::set("prova-01.login_throttle.lockout_message", "Locked.");

        // Register a simple route that uses the package middleware alias via the Route facade
        Route::post("/login-demo", function () {
            return response()->json(["ok" => true]);
        })->middleware("prova01.login.throttle");

        $email = "user@example.com";
        $identifier = "email|" . strtolower($email);
        $attemptsKey = "prova01:login_attempts:{$identifier}";
        $lockoutKey = "prova01:login_lockout:{$identifier}";

        // Ensure a clean slate
        Cache::forget($attemptsKey);
        Cache::forget($lockoutKey);

        // Perform failed login attempts; after each request simulate the Failed event
        $maxAttempts = config("prova-01.login_throttle.max_attempts");

        for ($i = 1; $i <= $maxAttempts; $i++) {
            // Build a request and dispatch it through the application to exercise the middleware
            $req = Request::create("/login-demo", "POST", [
                "email" => $email,
                "password" => "wrong",
            ]);
            $req->headers->set("Content-Type", "application/json");

            $response = app()->handle($req);
            // Expect the route to be reachable (middleware should allow until lockout is set)
            expect($response->getStatusCode())->toBe(200);

            // Simulate failed authentication attempt (this should trigger the listener and update cache)
            event(new Failed("web", null, ["email" => $email]));
        }

        // After reaching the threshold, the lockout key should be present in cache
        expect(Cache::has($lockoutKey))->toBeTrue();

        // Further requests with the same identifier should be blocked by the middleware
        $blockedReq = Request::create("/login-demo", "POST", [
            "email" => $email,
            "password" => "wrong",
        ]);
        $blockedReq->headers->set("Content-Type", "application/json");

        $blockedResponse = app()->handle($blockedReq);
        expect($blockedResponse->getStatusCode())->toBe(429);

        $json = json_decode((string) $blockedResponse->getContent(), true);
        expect($json)->toBeArray();
        expect($json)->toHaveKey("message");
        expect($json["message"])->toBe("Locked.");

        // Ensure Retry-After header is present and is a non-empty value
        $retryAfter = $blockedResponse->headers->get("Retry-After");
        expect($retryAfter)->not->toBeNull();
        expect((int) $retryAfter)->toBeGreaterThanOrEqual(0);

        // Simulate successful login to reset attempts
        event(new Login((object) ["email" => $email], false));

        // Now request should be allowed again
        $afterReq = Request::create("/login-demo", "POST", [
            "email" => $email,
            "password" => "correct",
        ]);
        $afterReq->headers->set("Content-Type", "application/json");

        $afterResponse = app()->handle($afterReq);
        expect($afterResponse->getStatusCode())->toBe(200);
    },
);
