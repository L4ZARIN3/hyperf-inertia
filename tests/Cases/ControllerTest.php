<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Test\Cases;

use FastRoute\Dispatcher;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\Handler;
use OnixSystemsPHP\HyperfInertia\Controller;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
#[CoversClass(Controller::class)]
class ControllerTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('swoole') && ! extension_loaded('swow')) {
            $this->markTestSkipped('The Controller requires a running Hyperf context (Swoole/Swow).');
        }

        parent::setUp();
    }

    public function testItRendersComponentFromRouteDefaults(): void
    {
        $handler = new Handler(Controller::class, '/docs', [
            'defaults' => [
                'component' => 'Docs',
                'props' => ['version' => '1.0'],
            ],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')
            ->willReturnCallback(fn ($name, $default = null) => $name === Dispatched::class ? new Dispatched([Dispatcher::FOUND, $handler, []]) : $default);

        $response = (new Controller())($request);

        $this->assertSame('Docs', $response->component);
        $this->assertSame(['version' => '1.0'], $response->props);
    }

    public function testItRendersEmptyComponentWithoutDefaults(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);

        $response = (new Controller())($request);

        $this->assertSame('', $response->component);
        $this->assertSame([], $response->props);
    }
}
