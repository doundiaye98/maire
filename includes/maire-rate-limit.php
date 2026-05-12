<?php
declare(strict_types=1);

/**
 * Limite simple par IP (fichier + flock) pour éviter saturation / abus sur des points sensibles (ex. formulaire de connexion).
 * En cas d’impossibilité d’écrire le fichier, on laisse passer (ne pas bloquer le site).
 */

function maire_rate_limit_allow(string $action, int $maxHits, int $windowSeconds): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0');
    if ($ip === '' || $maxHits <= 0 || $windowSeconds <= 0) {
        return true;
    }

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'maire_rl';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return true;
    }

    $key = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip . '_' . $action);
    $file = $dir . DIRECTORY_SEPARATOR . $key . '.rl';
    $fp = @fopen($file, 'c+');
    if ($fp === false) {
        return true;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);

        return true;
    }

    $raw = stream_get_contents($fp);
    $now = time();
    $cutoff = $now - $windowSeconds;
    $times = [];
    foreach (explode("\n", trim((string) $raw)) as $line) {
        $t = (int) $line;
        if ($t > $cutoff) {
            $times[] = $t;
        }
    }

    if (count($times) >= $maxHits) {
        flock($fp, LOCK_UN);
        fclose($fp);

        return false;
    }

    $times[] = $now;
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, implode("\n", $times));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}
