<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia;

use OnixSystemsPHP\HyperfInertia\Commands\InertiaInitCommand;
use OnixSystemsPHP\HyperfInertia\Commands\StartSsr;
use OnixSystemsPHP\HyperfInertia\Commands\StopSsr;
use OnixSystemsPHP\HyperfInertia\Listener\RegisterTestingMacrosListener;
use OnixSystemsPHP\HyperfInertia\View\Component\InertiaBody;
use OnixSystemsPHP\HyperfInertia\View\Component\InertiaHead;
use OnixSystemsPHP\HyperfInertia\View\Component\ReactRefresh;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [],
            'commands' => [
                StartSsr::class,
                StopSsr::class,
                InertiaInitCommand::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'listeners' => [
                RegisterTestingMacrosListener::class,
            ],

            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for onix-systems-php/hyperf-inertia.',
                    'source' => __DIR__ . '/../publish/inertia.php',
                    'destination' => BASE_PATH . '/config/autoload/inertia.php',
                ],
                [
                    'id' => 'middleware',
                    'description' => 'The middleware for onix-systems-php/hyperf-inertia.',
                    'source' => __DIR__ . '/../publish/HandleInertiaMiddleware.php',
                    'destination' => BASE_PATH . '/app/Common/Middleware/HandleInertiaMiddleware.php',
                ],
            ],

            'view' => [
                'components' => [
                    'inertia-body' => InertiaBody::class,
                    'inertia-head' => InertiaHead::class,
                    'react-refresh' => ReactRefresh::class,
                ],
            ],
        ];
    }
}
