<?php

declare(strict_types=1);

namespace glpzzz\cron\components;

use glpzzz\cron\attributes\Cron;
use glpzzz\cron\controllers\ManageController;
use glpzzz\cron\controllers\StatusController;
use glpzzz\cron\models\CronEntry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use Yii;
use yii\base\Component;
use yii\helpers\Inflector;

/**
 * Reflects over the configured controller directories and returns one {@see CronEntry} per
 * #[Cron] attribute found on a public `action*` method.
 *
 * `scan` maps a controller namespace prefix to a path (Yii alias ok), e.g.
 * `['console\controllers\cron' => '@console/controllers/cron']`.
 */
class CronScanner extends Component
{
    /**
     * @var array<string, string> namespace prefix => directory (alias allowed)
     */
    public array $scan = [];

    /**
     * Recurse into sub-directories of each scanned path (sub-namespaces are appended to the
     * prefix). Off by default -- a flat `*Controller.php` glob.
     */
    public bool $recursive = false;

    /**
     * @var string[] extra fully-qualified controller classes to skip. {@see ManageController}
     *     and the module's own web controller are always skipped.
     */
    public array $exclude = [];

    /**
     * @return CronEntry[]
     */
    public function scan(): array
    {
        $entries = [];
        foreach ($this->scan as $namespace => $path) {
            $baseNamespace = trim($namespace, '\\');
            $baseDir = Yii::getAlias($path);
            foreach ($this->controllerFiles($baseDir) as $file => $relativeNamespace) {
                $className = $baseNamespace
                    . ($relativeNamespace === '' ? '' : '\\' . $relativeNamespace)
                    . '\\' . basename($file, '.php');

                if (in_array($className, $this->skippedClasses(), true) || !class_exists($className)) {
                    continue;
                }

                $reflectionClass = new ReflectionClass($className);
                if ($reflectionClass->isAbstract()) {
                    continue;
                }
                $controllerId = Inflector::camel2id(str_replace('Controller', '', $reflectionClass->getShortName()));

                foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if (!str_starts_with($method->getName(), 'action') || $method->getName() === 'actions') {
                        continue;
                    }
                    $actionId = Inflector::camel2id(substr($method->getName(), 6));

                    foreach ($method->getAttributes(Cron::class) as $attribute) {
                        /** @var Cron $cron */
                        $cron = $attribute->newInstance();
                        $entries[] = new CronEntry(
                            controllerId: $controllerId,
                            actionId: $actionId,
                            params: $cron->params,
                            schedule: $cron->schedule,
                            description: $cron->description,
                        );
                    }
                }
            }
        }
        return $entries;
    }

    /**
     * @return string[]
     */
    private function skippedClasses(): array
    {
        return array_merge([ManageController::class, StatusController::class], $this->exclude);
    }

    /**
     * @return iterable<string, string> file path => sub-namespace relative to the base ('' when flat)
     */
    private function controllerFiles(string $baseDir): iterable
    {
        if (!$this->recursive) {
            foreach (glob($baseDir . '/*Controller.php') ?: [] as $file) {
                yield $file => '';
            }
            return;
        }

        if (!is_dir($baseDir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || !str_ends_with($item->getFilename(), 'Controller.php')) {
                continue;
            }
            $relativeDir = trim(str_replace($baseDir, '', $item->getPath()), '/');
            yield $item->getPathname() => str_replace('/', '\\', $relativeDir);
        }
    }
}
