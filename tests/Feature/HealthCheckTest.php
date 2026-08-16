<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * G-01: Health-Check als Grundlage des Deployment-Smoke-Tests.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_meldet_datenbank_und_migrationsstand(): void
    {
        $antwort = $this->getJson('/health')->assertOk();

        $antwort->assertJsonPath('status', 'ok');
        $antwort->assertJsonPath('db', 'up');
        $antwort->assertJsonPath('migrationen.offen', 0);
        // Bewusst wortkarg: Versionen und Migrationsnamen gehoeren nicht an
        // einen Endpunkt, den jeder ohne Anmeldung abrufen kann. Was der
        // Smoke-Test nach einem Deployment braucht, bleibt.
        $antwort->assertJsonStructure(['status', 'db', 'time', 'migrationen' => ['offen']]);
        $antwort->assertJsonMissingPath('laravel');
        $antwort->assertJsonMissingPath('php');
    }
}
