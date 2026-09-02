<?php

declare(strict_types=1);

namespace glpzzz\cron\components;

use RuntimeException;
use yii\base\Component;

/**
 * Thin wrapper around the system `crontab` binary. Reads and replaces the invoking user's
 * whole crontab; ownership of individual lines is decided by the caller (by matching the
 * command against the app path), not here -- there is no marker block.
 */
class Crontab extends Component
{
    /**
     * @return string[] raw crontab lines, in original order. Empty array if the user has no crontab yet.
     */
    public function readLines(): array
    {
        $output = [];
        $exitCode = 0;
        exec('crontab -l 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0 && $output !== []) {
            throw new RuntimeException("Failed to read crontab (exit code {$exitCode}).");
        }
        return $output;
    }

    /**
     * @param string[] $lines
     */
    public function writeLines(array $lines): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tmpFile, $lines === [] ? '' : implode("\n", $lines) . "\n");

        $exitCode = 0;
        $output = [];
        exec('crontab ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $exitCode);
        unlink($tmpFile);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to write crontab: ' . implode("\n", $output));
        }
    }

    /**
     * Parses a single crontab line into its schedule and command, or null if the
     * line is blank, a comment, or otherwise not a standard 5-field cron entry.
     *
     * @return array{schedule: string, command: string}|null
     */
    public static function parseLine(string $line): ?array
    {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return null;
        }
        if (!preg_match('/^((?:\S+\s+){4}\S+)\s+(.+)$/', $trimmed, $matches)) {
            return null;
        }
        return ['schedule' => $matches[1], 'command' => $matches[2]];
    }
}
