<?php

declare(strict_types=1);

use Algoritma\ShopwareQueryBuilder\Tests\TestKernel;
use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\TestBootstrapper;

$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';

// Assicurati che l'autoload sia disponibile
$autoloadPath = dirname(__DIR__, 4) . '/vendor/autoload.php';
if (! file_exists($autoloadPath)) {
    throw new RuntimeException('Composer dependencies are not installed. Run "composer install"');
}

/** @var ClassLoader $loader */
$loader = require $autoloadPath;
$loader->addPsr4('Algoritma\ShopwareQueryBuilder\Tests\\', __DIR__);
$loader->addPsr4('Algoritma\ShopwareQueryBuilder\\', __DIR__ . '/../src/');

require_once __DIR__ . '/../src/helpers.php';

$shopwareRoot = dirname(__DIR__, 4);

KernelLifecycleManager::prepare(require $autoloadPath);

KernelFactory::$kernelClass = TestKernel::class;

$_SERVER['KERNEL_CLASS'] = TestKernel::class;
$_ENV['KERNEL_CLASS'] = TestKernel::class;

// Ora bootstrappa Shopware
(new TestBootstrapper())
    ->setProjectDir($shopwareRoot)
    ->setLoadEnvFile(false)
    ->setDatabaseUrl('mysql://root:root@127.0.0.1:3306/shopware')
    ->setForceInstallPlugins(false)
    ->setPlatformEmbedded(false)
    ->bootstrap();
