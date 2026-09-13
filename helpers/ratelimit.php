<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

function client_ip(): string
{
    // Don't read X-Forwarded-For here — it's spoofable by any client.
    // If you're behind a trusted proxy (e.g. Cloudflare) you may read
    // CF-Connecting-IP, but ONLY if the reverse proxy is the only path to you.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Checks and records an attempt. Sends 429 if the limit is exceeded.
 */
function rate_limit(string $action, string $identifier, int $max, int $windowSec): void
{
    $pdo = db();

    // Occasional cleanup: delete rows older than 24h
    if (random_int(1, 20) === 1) {
        $pdo->prepare('DELETE FROM rate_limits WHERE created_at < ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM rate_limits
         WHERE action = ? AND identifier = ? AND created_at > ?'
    );
    $stmt->execute([$action, $identifier, date('Y-m-d H:i:s', time() - $windowSec)]);

    if ((int) $stmt->fetchColumn() >= $max) {
        json_error('Prea multe încercări. Revino peste câteva minute.', 429);
    }

    $pdo->prepare('INSERT INTO rate_limits (action, identifier, created_at) VALUES (?, ?, ?)')
        ->execute([$action, $identifier, date('Y-m-d H:i:s')]);
}

/** Clears the attempt history (e.g. after a successful login). */
function rate_limit_clear(string $action, string $identifier): void
{
    db()->prepare('DELETE FROM rate_limits WHERE action = ? AND identifier = ?')
        ->execute([$action, $identifier]);
}