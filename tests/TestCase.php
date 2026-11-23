<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests share one session across requests, but in the app each person has their own.
     * The auth.session middleware pins a session to one user's password hash, so switching
     * users mid-test drops the previous user's hash, as a fresh login would.
     */
    public function be(Authenticatable $user, $guard = null)
    {
        if ($this->app->resolved('session')) {
            $this->app['session']->forget('password_hash_'.($guard ?? config('auth.defaults.guard')));
        }

        return parent::be($user, $guard);
    }
}
