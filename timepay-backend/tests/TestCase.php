<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->forceIsolatedTestEnvironment();

        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->useBootstrapPath(__DIR__.'/fixtures/bootstrap');
        if (DIRECTORY_SEPARATOR === '\\') {
            $app->addAbsoluteCachePathPrefix(substr(__DIR__, 0, 3));
        }
        $app->useEnvironmentPath(__DIR__.'/fixtures');
        $app->loadEnvironmentFrom('testing.env');
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    private function forceIsolatedTestEnvironment(): void
    {
        $cachePath = __DIR__.'/fixtures/bootstrap/cache/';

        foreach ([
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
            'APP_CONFIG_CACHE' => $cachePath.'config.php',
            'APP_SERVICES_CACHE' => $cachePath.'services.php',
            'APP_PACKAGES_CACHE' => $cachePath.'packages.php',
            'APP_ROUTES_CACHE' => $cachePath.'routes-v7.php',
            'APP_EVENTS_CACHE' => $cachePath.'events.php',
        ] as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
