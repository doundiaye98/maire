<?php
declare(strict_types=1);

namespace MaireRufisqueEst\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests du système de rate limiting.
 */
final class RateLimitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'maire-rate-limit.php';
    }

    protected function setUp(): void
    {
        // IP stable et unique par test pour isoler chaque cas
        $_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 250);
        // Purge tout fichier de rate-limit résiduel pour cette IP
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'maire_rl';
        if (is_dir($dir)) {
            foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*.rl') as $f) {
                if (is_string($f) && strpos((string) file_get_contents($f), (string) $_SERVER['REMOTE_ADDR']) !== false) {
                    @unlink($f);
                }
            }
        }
    }

    public function testFirstRequestIsAlwaysAllowed(): void
    {
        $this->assertTrue(maire_rate_limit_allow('login_test', 3, 60));
    }

    public function testRequestsUnderLimitAreAllowed(): void
    {
        $this->assertTrue(maire_rate_limit_allow('login_test', 3, 60));
        $this->assertTrue(maire_rate_limit_allow('login_test', 3, 60));
        $this->assertTrue(maire_rate_limit_allow('login_test', 3, 60));
    }

    public function testRequestsExceedingLimitAreBlocked(): void
    {
        $this->assertTrue(maire_rate_limit_allow('login_test2', 2, 60));
        $this->assertTrue(maire_rate_limit_allow('login_test2', 2, 60));
        $this->assertFalse(maire_rate_limit_allow('login_test2', 2, 60), '3e tentative doit être bloquée');
        $this->assertFalse(maire_rate_limit_allow('login_test2', 2, 60), '4e tentative aussi');
    }

    public function testZeroMaxHitsAllowsThrough(): void
    {
        // Cas dégradé : si on désactive le rate-limit (0), on doit toujours laisser passer.
        $this->assertTrue(maire_rate_limit_allow('login_test3', 0, 60));
        $this->assertTrue(maire_rate_limit_allow('login_test3', 0, 60));
    }

    public function testDifferentActionsHaveSeparateBuckets(): void
    {
        $this->assertTrue(maire_rate_limit_allow('action_A', 1, 60));
        $this->assertFalse(maire_rate_limit_allow('action_A', 1, 60));
        // Une action différente doit avoir son propre compteur
        $this->assertTrue(maire_rate_limit_allow('action_B', 1, 60));
    }
}
