<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Test\Cases;

use OnixSystemsPHP\HyperfInertia\Inertia;
use OnixSystemsPHP\HyperfInertia\InertiaResponse;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
class HelpersTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('swoole') && ! extension_loaded('swow')) {
            $this->markTestSkipped('The inertia() helper requires a running Hyperf context (Swoole/Swow).');
        }

        parent::setUp();
    }

    public function testInertiaHelperReturnsInertiaInstanceWithoutComponent(): void
    {
        $this->assertInstanceOf(Inertia::class, inertia());
    }

    public function testInertiaHelperReturnsResponseWithComponent(): void
    {
        $response = inertia('Dashboard', ['foo' => 'bar']);

        $this->assertInstanceOf(InertiaResponse::class, $response);
        $this->assertSame('Dashboard', $response->component);
    }
}
