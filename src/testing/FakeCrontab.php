<?php

declare(strict_types=1);

namespace glpzzz\cron\testing;

use glpzzz\cron\components\Crontab;

/**
 * In-memory {@see Crontab} for tests. Bind it as the module's `crontab` component in the
 * test config so nothing shells out to the real `crontab` binary.
 */
class FakeCrontab extends Crontab
{
    /** @var string[] */
    private array $_lines = [];

    public function readLines(): array
    {
        return $this->_lines;
    }

    public function writeLines(array $lines): void
    {
        $this->_lines = array_values($lines);
    }

    public function reset(): void
    {
        $this->_lines = [];
    }
}
