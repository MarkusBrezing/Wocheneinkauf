<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
});

/*
 * Health-Check (G-01): DB erreichbar, Version, Migrationsstand.
 * Grundlage fuer den Smoke-Test nach jedem Deployment.
 */
Route::get('/health', function () {
    $db = 'down';
    $migrationen = null;

    try {
        DB::connection()->getPdo();
        $db = 'up';

        $migrator = app('migrator');
        $ausgefuehrt = $migrator->getRepository()->getRan();
        $vorhanden = array_keys($migrator->getMigrationFiles([database_path('migrations')]));

        $migrationen = [
            'ausgefuehrt' => count($ausgefuehrt),
            'offen' => count(array_diff($vorhanden, $ausgefuehrt)),
            'letzte' => empty($ausgefuehrt) ? null : end($ausgefuehrt),
        ];
    } catch (\Throwable $e) {
        $db = 'down';
    }

    // Offene Migrationen bedeuten: der Code ist neuer als das Schema. Das ist
    // kein Ausfall, aber ein Deployment, das nur halb durchgelaufen ist.
    $gesund = $db === 'up' && ($migrationen['offen'] ?? 0) === 0;

    return response()->json([
        'status' => $gesund ? 'ok' : 'degraded',
        'db' => $db,
        'migrationen' => $migrationen,
        'app' => config('app.name'),
        'laravel' => app()->version(),
        'php' => PHP_VERSION,
        'time' => now()->toIso8601String(),
    ], $gesund ? 200 : 503);
});

/*
 * Einladungen werden auch von Gaesten geoeffnet -- wer eingeladen wird, hat in
 * der Regel noch kein Konto (A-07).
 */
Route::get('/einladung/{token}', [InvitationController::class, 'show'])->name('invitation.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/einladung/{token}', [InvitationController::class, 'accept'])->name('invitation.accept');

    // Familie: Einstellungen (A-10), Mitglieder (A-08), Wechsel (A-13)
    Route::get('/familie', [FamilyController::class, 'edit'])->name('family.edit');
    Route::put('/familie', [FamilyController::class, 'update'])->name('family.update');
    Route::post('/familie/wechseln', [FamilyController::class, 'switch'])->name('family.switch');
    Route::patch('/familie/mitglieder/{mitglied}', [FamilyController::class, 'updateMember'])->name('family.members.update');
    Route::delete('/familie/mitglieder/{mitglied}', [FamilyController::class, 'destroyMember'])->name('family.members.destroy');

    // Einladungen (A-06, A-09)
    Route::post('/familie/einladungen', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('/familie/einladungen/{einladung}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    // Konto: Auskunft und Loeschung (A-11, A-12)
    Route::get('/konto', [AccountController::class, 'edit'])->name('account.edit');
    Route::get('/konto/auskunft', [AccountController::class, 'export'])->name('account.export');
    Route::delete('/konto', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::delete('/konto/familie', [AccountController::class, 'destroyFamily'])->name('account.family.destroy');
});
