<?php

declare(strict_types=1);

namespace glpzzz\cron\testing;

use glpzzz\cron\components\CronScanner;
use glpzzz\cron\models\CronEntry;

/**
 * {@see CronScanner} that returns a fixed set of entries instead of reflecting the filesystem.
 * Bind it as the module's `cronScanner` component in the test config.
 */
class FakeCronScanner extends CronScanner
{
    /** @var CronEntry[] */
    private array $_entries = [];

    /**
     * @param CronEntry[] $entries
     */
    public function setEntries(array $entries): void
    {
        $this->_entries = $entries;
    }

    public function scan(): array
    {
        return $this->_entries;
    }
}
