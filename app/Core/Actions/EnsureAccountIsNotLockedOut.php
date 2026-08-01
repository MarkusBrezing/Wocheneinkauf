<?php

namespace App\Core\Actions;

use App\Core\Services\LoginAttemptRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Haelt gesperrte Konten aus der Anmeldung heraus (A-14).
 *
 * Ergaenzt Fortifys throttle-Middleware um eine Sperre, die in login_attempts
 * steht statt im Cache -- sie ueberlebt damit einen Neustart und ein
 * "php artisan cache:clear".
 */
class EnsureAccountIsNotLockedOut
{
    public function __construct(protected LoginAttemptRecorder $recorder) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $email = (string) $request->input(Fortify::username(), '');

        if (! $this->recorder->isLockedOut($email, $request->ip())) {
            return $next($request);
        }

        throw ValidationException::withMessages([
            Fortify::username() => trans('auth.throttle', [
                'seconds' => LoginAttemptRecorder::LOCKOUT_MINUTES * 60,
                'minutes' => LoginAttemptRecorder::LOCKOUT_MINUTES,
            ]),
        ]);
    }
}
