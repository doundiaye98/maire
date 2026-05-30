<?php
declare(strict_types=1);

namespace MaireRufisqueEst\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;
use RuntimeException;

/**
 * Tests du système de migrations.
 *
 * On utilise une BDD SQLite en mémoire pour la rapidité. Le système
 * de migrations est rétrocompatible SQLite/MySQL pour les opérations
 * de base (CREATE TABLE, INSERT, UPDATE, DELETE).
 */
final class MigrationsTest extends TestCase
{
    private PDO $pdo;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->pdo = maire_test_pdo_memory();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'maire_mig_' . uniqid();
        @mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        // Nettoyage
        foreach ((array) @glob($this->tmpDir . DIRECTORY_SEPARATOR . '*') as $f) {
            if (is_string($f)) {
                @unlink($f);
            }
        }
        @rmdir($this->tmpDir);
    }

    public function testEnsureTableCreatesSchemaMigrations(): void
    {
        maire_migrations_ensure_table($this->pdo);
        $r = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='schema_migrations'")->fetch();
        $this->assertNotFalse($r);
        $this->assertSame('schema_migrations', $r['name']);
    }

    public function testAppliedListIsEmptyOnFreshDatabase(): void
    {
        $applied = maire_migrations_applied($this->pdo);
        $this->assertSame([], $applied);
    }

    public function testInsertingMigrationRecordWorks(): void
    {
        maire_migrations_ensure_table($this->pdo);
        $this->pdo->prepare('INSERT INTO schema_migrations (version, description, execution_ms) VALUES (?, ?, ?)')
            ->execute(['2026_05_12_120000_test_migration', 'Test description', 42]);
        $applied = maire_migrations_applied($this->pdo);
        $this->assertSame(['2026_05_12_120000_test_migration'], $applied);
    }

    public function testStatusReturnsBothAppliedAndPending(): void
    {
        maire_migrations_ensure_table($this->pdo);
        $this->pdo->prepare('INSERT INTO schema_migrations (version, description, execution_ms) VALUES (?, ?, ?)')
            ->execute(['2026_01_01_000000_baseline', '', 0]);

        $status = maire_migrations_status($this->pdo);
        $this->assertArrayHasKey('applied', $status);
        $this->assertArrayHasKey('pending', $status);
        $this->assertContains('2026_01_01_000000_baseline', $status['applied']);
    }

    public function testMigrationsAvailableLooksUpRealFolderAndIsSorted(): void
    {
        // Les vraies migrations du projet doivent être triées par version (croissant).
        $available = maire_migrations_available();
        if (empty($available)) {
            $this->markTestSkipped('Aucune migration physique dans le projet (skip).');
        }
        $versions = array_column($available, 'version');
        $sorted = $versions;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $versions, 'Les migrations doivent être triées par version (ordre lexicographique).');
    }
}
