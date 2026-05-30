<?php
declare(strict_types=1);

namespace MaireRufisqueEst\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests du système CSRF centralisé.
 */
final class CsrfTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        session_id('csrf-test-' . bin2hex(random_bytes(8)));
        session_start();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        $_POST = [];
    }

    public function testTokenIsGeneratedAndStableForScope(): void
    {
        $a = maire_csrf_token('test_scope');
        $b = maire_csrf_token('test_scope');
        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
    }

    public function testValidateAcceptsMatchingPost(): void
    {
        $scope = 'contact_public';
        $token = maire_csrf_token($scope);
        $_POST = ['csrf_scope' => $scope, 'csrf_token' => $token];
        $this->assertTrue(maire_csrf_validate($scope));
    }

    public function testValidateRejectsWrongScope(): void
    {
        $token = maire_csrf_token('scope_a');
        $_POST = ['csrf_scope' => 'scope_b', 'csrf_token' => $token];
        $this->assertFalse(maire_csrf_validate('scope_a'));
    }

    public function testValidateRejectsTamperedToken(): void
    {
        $scope = 'citoyen';
        maire_csrf_token($scope);
        $_POST = ['csrf_scope' => $scope, 'csrf_token' => str_repeat('a', 64)];
        $this->assertFalse(maire_csrf_validate($scope));
    }

    public function testLegacyCitoyenTokenIsMigratedAndAccepted(): void
    {
        $_SESSION['citoyen_csrf'] = bin2hex(random_bytes(32));
        $legacy = (string) $_SESSION['citoyen_csrf'];
        $_POST = ['csrf' => $legacy];
        $this->assertTrue(maire_csrf_validate(MAIRE_CSRF_SCOPE_CITOYEN));
        $this->assertSame($legacy, $_SESSION[MAIRE_CSRF_SESSION_KEY][MAIRE_CSRF_SCOPE_CITOYEN] ?? '');
    }

    public function testLegacyAdminTokenIsMigratedAndAccepted(): void
    {
        $_SESSION['abo_admin_csrf'] = bin2hex(random_bytes(32));
        $legacy = (string) $_SESSION['abo_admin_csrf'];
        $_POST = ['csrf' => $legacy];
        $this->assertTrue(maire_csrf_validate(MAIRE_CSRF_SCOPE_ADMIN));
        $this->assertSame($legacy, $_SESSION[MAIRE_CSRF_SESSION_KEY][MAIRE_CSRF_SCOPE_ADMIN] ?? '');
    }

    public function testCsrfFieldContainsHiddenInputs(): void
    {
        $html = maire_csrf_field('demo_form');
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('name="csrf_scope"', $html);
        $this->assertStringContainsString('value="demo_form"', $html);
    }
}
