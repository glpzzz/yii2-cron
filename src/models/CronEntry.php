<?php

declare(strict_types=1);

namespace glpzzz\cron\models;

/**
 * Immutable description of one #[Cron]-tagged action: the route it maps to, its schedule
 * (null = documented but not installable), positional params and human description.
 */
final class CronEntry
{
    /**
     * @param string[] $params
     */
    public function __construct(
        public readonly string $controllerId,
        public readonly string $actionId,
        public readonly array $params,
        public readonly ?string $schedule,
        public readonly string $description,
    ) {
    }

    public function getRoute(string $routePrefix): string
    {
        return "{$routePrefix}/{$this->controllerId}/{$this->actionId}";
    }

    public function isScheduled(): bool
    {
        return $this->schedule !== null;
    }

    /**
     * The generated crontab command. `PHP_BINARY` is the interpreter that ran the install
     * command -- so whichever `php`/`ea-phpXX` executes `cron/manage/install*` on deploy is
     * exactly what lands in the crontab line.
     */
    public function getCommand(string $appPath, string $routePrefix): string
    {
        $args = array_map('escapeshellarg', [$this->getRoute($routePrefix), ...$this->params]);
        return 'cd ' . escapeshellarg($appPath)
            . ' && ' . escapeshellarg(PHP_BINARY) . ' yii ' . implode(' ', $args)
            . ' > /dev/null 2>&1';
    }
}
