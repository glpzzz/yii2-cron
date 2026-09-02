<?php

declare(strict_types=1);

namespace glpzzz\cron\tests\support;

use glpzzz\cron\components\Crontab;
use RuntimeException;

/**
 * A {@see Crontab} whose read fails -- stands in for "no crontab binary" / "not this user's
 * crontab" so the web status page's degraded path can be tested.
 */
class ThrowingCrontab extends Crontab
{
    public function readLines(): array
    {
        throw new RuntimeException('crontab: command not found');
    }
}
