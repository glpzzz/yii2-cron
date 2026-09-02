<?php

declare(strict_types=1);

namespace glpzzz\cron\tests;

use glpzzz\cron\Module;
use glpzzz\cron\testing\FakeCronScanner;
use glpzzz\cron\testing\FakeCrontab;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Yii;
use yii\console\Application as ConsoleApplication;
use yii\web\Application as WebApplication;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Yii::$app = null;
        Yii::$container->clear(Module::class);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $moduleConfig extra config merged into the `cron` module
     */
    protected function mockConsoleApplication(array $moduleConfig = []): ConsoleApplication
    {
        return new ConsoleApplication($this->appConfig($moduleConfig));
    }

    /**
     * @param array<string, mixed> $moduleConfig extra config merged into the `cron` module
     */
    protected function mockWebApplication(array $moduleConfig = []): WebApplication
    {
        $config = $this->appConfig($moduleConfig);
        $config['components']['request'] = [
            'cookieValidationKey' => 'test',
            'scriptFile' => __DIR__ . '/index.php',
            'scriptUrl' => '/index.php',
        ];
        return new WebApplication($config);
    }

    /**
     * @param array<string, mixed> $moduleConfig
     * @return array<string, mixed>
     */
    private function appConfig(array $moduleConfig): array
    {
        Yii::setAlias('@glpzzz/cron/tests/support', __DIR__ . '/support');

        return [
            'id' => 'cron-test',
            'basePath' => __DIR__,
            'vendorPath' => dirname(__DIR__) . '/vendor',
            'modules' => [
                'cron' => array_merge([
                    'class' => Module::class,
                    'controllerNamespace' => 'glpzzz\\cron\\tests\\support',
                    'scan' => [
                        'glpzzz\\cron\\tests\\support' => '@glpzzz/cron/tests/support',
                    ],
                ], $moduleConfig),
            ],
        ];
    }

    /**
     * @return array<string, mixed> module config fragment binding the in-memory fakes
     */
    protected function fakeComponents(): array
    {
        return [
            'components' => [
                'crontab' => ['class' => FakeCrontab::class],
                'cronScanner' => ['class' => FakeCronScanner::class],
            ],
        ];
    }
}
