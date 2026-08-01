<?php

namespace App\Core\Listeners;

use App\Core\Models\User;
use App\Core\Services\LoginAttemptRecorder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Schreibt jeden Anmeldeversuch in login_attempts (A-14) und haelt
 * users.last_login_at aktuell.
 */
class RecordLoginAttempt
{
    public function __construct(
        protected LoginAttemptRecorder $recorder,
        protected Request $request,
    ) {}

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return;
        }

        $this->recorder->record($email, $this->request->ip(), successful: false);
    }

    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->recorder->record($user->email, $this->request->ip(), successful: true);

        $user->forceFill(['last_login_at' => now()])->save();
    }
}
