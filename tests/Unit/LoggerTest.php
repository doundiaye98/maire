<?php
declare(strict_types=1);

namespace MaireRufisqueEst\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests du logger applicatif.
 */
final class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        // Vide le log du jour pour partir propre
        $dir = maire_log_directory();
        $today = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        if (is_file($today)) {
            @unlink($today);
        }
    }

    public function testLogDirectoryIsCreatedWithHtaccess(): void
    {
        $dir = maire_log_directory();
        $this->assertDirectoryExists($dir);
        $this->assertFileExists($dir . DIRECTORY_SEPARATOR . '.htaccess');
        $htContent = (string) file_get_contents($dir . DIRECTORY_SEPARATOR . '.htaccess');
        $this->assertStringContainsString('Require all denied', $htContent);
    }

    public function testInfoLogIsWrittenAsJsonLine(): void
    {
        maire_log_info('Test message', ['user_id' => 42]);
        $file = maire_log_directory() . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $this->assertFileExists($file);

        $line = trim((string) file_get_contents($file));
        $this->assertNotSame('', $line);
        $decoded = json_decode($line, true);
        $this->assertIsArray($decoded);
        $this->assertSame('INFO', $decoded['level']);
        $this->assertSame('Test message', $decoded['message']);
        $this->assertSame(42, $decoded['context']['user_id']);
        $this->assertArrayHasKey('cid', $decoded);
        $this->assertArrayHasKey('ts', $decoded);
    }

    public function testPlaceholderInterpolation(): void
    {
        maire_log_info('Document {id} créé par {email}', ['id' => 7, 'email' => 'admin@rufisque.sn']);
        $file = maire_log_directory() . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $content = (string) file_get_contents($file);
        $this->assertStringContainsString('Document 7 créé par admin@rufisque.sn', $content);
    }

    public function testDebugLogIsFilteredWhenLevelIsHigher(): void
    {
        // Le bootstrap fixe MAIRE_LOG_LEVEL=warning → debug doit être ignoré.
        maire_log_debug('Ne doit pas apparaître');
        $file = maire_log_directory() . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        if (is_file($file)) {
            $this->assertStringNotContainsString('Ne doit pas apparaître', (string) file_get_contents($file));
        } else {
            $this->assertTrue(true, 'Aucun log écrit — comportement attendu pour debug filtré.');
        }
    }

    public function testExceptionLogIncludesClassAndTrace(): void
    {
        try {
            throw new \RuntimeException('Boom !');
        } catch (\Throwable $e) {
            maire_log_exception($e, 'Erreur de test');
        }
        $file = maire_log_directory() . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $content = (string) file_get_contents($file);
        $this->assertStringContainsString('RuntimeException', $content);
        $this->assertStringContainsString('Erreur de test', $content);
        $this->assertStringContainsString('Boom !', $content);
    }

    public function testCorrelationIdIsStableWithinRequest(): void
    {
        $cid1 = maire_log_correlation_id();
        $cid2 = maire_log_correlation_id();
        $this->assertSame($cid1, $cid2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $cid1);
    }
}
