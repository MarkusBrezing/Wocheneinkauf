<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Core\Actions\EnsureAccountIsNotLockedOut;
use App\Core\Listeners\RecordLoginAttempt;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->registerViews();
        $this->registerRateLimiting();
        $this->registerLoginPipeline();

        // Die Listener liegen unter app/Core/Listeners und werden deshalb nicht
        // automatisch gefunden -- die Erkennung durchsucht nur app/Listeners.
        Event::listen(Failed::class, [RecordLoginAttempt::class, 'handleFailed']);
        Event::listen(Login::class, [RecordLoginAttempt::class, 'handleLogin']);
    }

    /**
     * Fortify bringt die Logik mit, aber keine Oberflaeche. Die Formulare sind
     * bewusst schlank gehalten: die App bekommt spaeter das bestehende
     * Vanilla-JS-Frontend, das dieselben Endpunkte als JSON anspricht.
     */
    protected function registerViews(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
    }

    /**
     * Erste Bremse gegen Brute Force (A-14): Fortifys Zaehler im Cache, fuenf
     * Versuche je Minute und E-Mail/IP-Kombination. Faengt schnelles
     * Durchprobieren ab, ist aber nach einem "cache:clear" wieder bei null.
     */
    protected function registerRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input(Fortify::username(), '');

            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower($email).'|'.$request->ip())
            );
        });
    }

    /**
     * Zweite Bremse (A-14): die dauerhafte Sperre aus der Tabelle
     * login_attempts.
     *
     * Sie kann nicht ueber den Rate Limiter laufen. Ein Limit mit null
     * erlaubten Versuchen blockt naemlich nicht: RateLimiter::tooManyAttempts
     * setzt den Zaehler zurueck, solange kein Timer-Key im Cache liegt -- und
     * genau der fehlt beim ersten Versuch nach einem Neustart. Deshalb haengt
     * die Pruefung als eigener Schritt in der Anmeldekette, wo sie ausserdem
     * eine verstaendliche Fehlermeldung erzeugen kann statt eines nackten 429.
     */
    protected function registerLoginPipeline(): void
    {
        Fortify::authenticateThrough(fn () => array_filter([
            EnsureAccountIsNotLockedOut::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));
    }
}
