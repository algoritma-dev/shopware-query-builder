<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Composer\IO\NullIO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[CoversNothing]
class PluginLifecycleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private ContainerInterface $container;

    private PluginLifecycleService $pluginLifecycleService;

    private PluginService $pluginService;

    private Context $context;

    protected function setUp(): void
    {
        $this->container = $this->getContainer();
        $this->pluginLifecycleService = $this->container->get(PluginLifecycleService::class);
        $this->pluginService = $this->container->get(PluginService::class);
        $this->context = Context::createCLIContext();
    }

    public function testPluginLifecycle(): void
    {
        $pluginName = 'AlgoritmaSwQueryBuilder';

        // Refresh plugins to ensure our plugin is in the database
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginService->getPluginByName($pluginName, $this->context);
        $this->assertNotNull($plugin, 'Plugin AlgoritmaSwQueryBuilder not found');

        // 1. Install
        if ($plugin->getInstalledAt() === null) {
            $this->pluginLifecycleService->installPlugin($plugin, $this->context);
            $plugin = $this->pluginService->getPluginByName($pluginName, $this->context);
            $this->assertNotNull($plugin->getInstalledAt(), 'Plugin should be installed');
            $this->assertFalse($plugin->getActive(), 'Plugin should not be active after installation');
        }

        // 2. Activate
        if (! $plugin->getActive()) {
            $this->pluginLifecycleService->activatePlugin($plugin, $this->context, false);
            $plugin = $this->pluginService->getPluginByName($pluginName, $this->context);
            $this->assertTrue($plugin->getActive(), 'Plugin should be active');
        }

        // Verify service locator is registered in the container when active
        $this->assertTrue($this->container->has('algoritma.query_builder.service_locator'), 'Service locator should be registered');

        // Check if helper function works
        $this->assertTrue(function_exists('sw_query'), 'sw_query helper should exist');

        // 3. Deactivate
        $this->pluginLifecycleService->deactivatePlugin($plugin, $this->context);
        $plugin = $this->pluginService->getPluginByName($pluginName, $this->context);
        $this->assertFalse($plugin->getActive(), 'Plugin should be inactive');

        // 4. Uninstall
        $this->pluginLifecycleService->uninstallPlugin($plugin, $this->context, true);
        $plugin = $this->pluginService->getPluginByName($pluginName, $this->context);
        $this->assertNull($plugin->getInstalledAt(), 'Plugin should be uninstalled');
    }
}
