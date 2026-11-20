<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Test\Cases;

use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use OnixSystemsPHP\HyperfInertia\Test\TestContainer;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    protected TestContainer $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new TestContainer();
        $this->container->set(ConfigInterface::class, new Config($this->config()));
        ApplicationContext::setContainer($this->container);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * The application config used by the tests.
     */
    protected function config(): array
    {
        return [
            'inertia' => [
                'root_view' => 'inertia',
                'public_path_prefix' => sys_get_temp_dir() . '/inertia-test-public',
                'asset_public_path' => '/public',
                'skip_url_prefix' => [],
                'no_skip_extra_path' => [],
                'is_secure' => true,
            ],
        ];
    }
}
