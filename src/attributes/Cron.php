<?php

declare(strict_types=1);

namespace glpzzz\cron\attributes;

use Attribute;

/**
 * Tags a console controller action as manageable by the cron module.
 *
 * Repeatable: put several #[Cron] on one action to schedule it more than once with
 * different parameters (e.g. a "daily" and a "weekly" variant of the same command).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Cron
{
    /**
     * @param string $description Required. Shown in `cron/manage/list` and the web status page.
     * @param string|null $schedule 5-field cron expression, or null to mark this action as
     *     cron-manageable without actually scheduling it -- discoverable/documented via #[Cron],
     *     but skipped by install/install-all since there is nothing to install.
     * @param string[] $params Positional args, baked into the generated command verbatim.
     */
    public function __construct(
        public readonly string $description,
        public readonly ?string $schedule = null,
        public readonly array $params = [],
    ) {
    }
}
