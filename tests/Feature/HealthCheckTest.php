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
        $antwort->assertJsonStructure([
            'status', 'db', 'app', 'laravel', 'php', 'time',
            'migrationen' => ['ausgefuehrt', 'offen', 'letzte'],
        ]);

        $this->assertGreaterThan(0, $antwort->json('migrationen.ausgefuehrt'));
    }
}
