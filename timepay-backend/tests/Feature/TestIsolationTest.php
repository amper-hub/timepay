<?php

namespace Tests\Feature;

use Composer\Autoload\ClassLoader;
use Tests\TestCase;

class TestIsolationTest extends TestCase
{
    public function test_conflicting_database_environment_is_overridden_before_application_boot(): void
    {
        $names = ['DB_CONNECTION', 'DB_DATABASE', 'DB_URL'];
        $original = [];

        foreach ($names as $name) {
            $original[$name] = getenv($name);
            putenv($name.'='.match ($name) {
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => 'persistent_payroll',
                default => 'mysql://invalid:invalid@127.0.0.1/persistent_payroll',
            });
            $_ENV[$name] = getenv($name);
            $_SERVER[$name] = getenv($name);
        }

        try {
            $isolatedApp = $this->createApplication();
            $database = $isolatedApp->make('db');
            $connection = $database->connection();
            $pdo = $connection->getPdo();

            $this->assertSame('sqlite', $isolatedApp['config']['database.default']);
            $this->assertSame(':memory:', $connection->getDatabaseName());
            $this->assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
            $this->assertSame(realpath(dirname(__DIR__, 2).'/tests/fixtures/bootstrap'), realpath($isolatedApp->bootstrapPath()));
            $this->assertFalse($isolatedApp->configurationIsCached());
        } finally {
            foreach ($original as $name => $value) {
                if ($value === false) {
                    putenv($name);
                    unset($_ENV[$name], $_SERVER[$name]);
                } else {
                    putenv($name.'='.$value);
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }

    public function test_project_and_vendor_classes_resolve_only_from_this_checkout(): void
    {
        /** @var ClassLoader $loader */
        $loader = $GLOBALS['timepay_project_autoloader'];
        $projectRoot = realpath(dirname(__DIR__, 2));
        $userClassPath = realpath($loader->findFile(\App\Models\User::class));
        $thirdPartyClassPath = realpath($loader->findFile(\Illuminate\Support\Str::class));

        $this->assertSame(realpath($projectRoot.'/app/Models/User.php'), $userClassPath);
        $this->assertStringStartsWith($projectRoot.DIRECTORY_SEPARATOR, $userClassPath);
        $this->assertStringStartsWith(realpath($projectRoot.'/vendor').DIRECTORY_SEPARATOR, $thirdPartyClassPath);
        $this->assertFalse($loader->findFile('App\\Events\\AttendanceRejected'));
        $this->assertFalse($loader->findFile('Tests\\Feature\\ClassFromAnotherCheckout'));

        foreach ($loader->getClassMap() as $class => $path) {
            if (! str_starts_with($class, 'App\\') && ! str_starts_with($class, 'Tests\\')) {
                continue;
            }

            $resolvedPath = realpath($path);
            $this->assertNotFalse($resolvedPath);
            $this->assertStringStartsWith($projectRoot.DIRECTORY_SEPARATOR, $resolvedPath);
        }
    }

}
