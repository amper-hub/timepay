<?php

use Composer\Autoload\ClassLoader;

$projectRoot = realpath(dirname(__DIR__));
$vendorRoot = realpath($projectRoot.'/vendor');

if ($projectRoot === false || $vendorRoot === false) {
    throw new RuntimeException('The project and its local vendor directory must resolve on disk.');
}

$projectRootWithSeparator = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
if (! str_starts_with($vendorRoot, $projectRootWithSeparator)) {
    throw new RuntimeException('Composer vendor files must resolve inside this project checkout.');
}

$loader = require $vendorRoot.'/autoload.php';

if (! $loader instanceof ClassLoader) {
    throw new RuntimeException('Composer did not return its class loader.');
}

$classMapProperty = new ReflectionProperty(ClassLoader::class, 'classMap');
$classMap = $classMapProperty->getValue($loader);

foreach ($classMap as $class => $path) {
    if (! str_starts_with($class, 'App\\') && ! str_starts_with($class, 'Tests\\')) {
        continue;
    }

    $resolvedPath = realpath($path);
    if ($resolvedPath === false || ! is_file($resolvedPath) || ! str_starts_with($resolvedPath, $projectRootWithSeparator)) {
        unset($classMap[$class]);
    }
}

$classMapProperty->setValue($loader, $classMap);
$loader->setPsr4('App\\', $projectRoot.'/app');
$loader->setPsr4('Tests\\', $projectRoot.'/tests');
$GLOBALS['timepay_project_autoloader'] = $loader;

