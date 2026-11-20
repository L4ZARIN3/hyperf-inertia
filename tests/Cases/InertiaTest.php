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
use OnixSystemsPHP\HyperfInertia\LazyProp;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(Inertia::class)]
class InertiaTest extends AbstractTestCase
{
    public function testItSharesArrayOfProps(): void
    {
        $inertia = new Inertia();
        $inertia->share(['foo' => 'bar', 'baz' => 1]);

        $this->assertSame(['foo' => 'bar', 'baz' => 1], $inertia->getShared());
    }

    public function testItSharesDotNotationKey(): void
    {
        $inertia = new Inertia();
        $inertia->share('errors.name', 'John');

        $this->assertSame(['errors' => ['name' => 'John']], $inertia->getShared());
        $this->assertSame('John', $inertia->getShared('errors.name'));
    }

    public function testItFlushesSharedProps(): void
    {
        $inertia = new Inertia();
        $inertia->share('foo', 'bar');
        $inertia->flushShared();

        $this->assertSame([], $inertia->getShared());
    }

    public function testItSetsAndResolvesVersion(): void
    {
        $inertia = new Inertia();
        $inertia->version('abc123');

        $this->assertSame('abc123', $inertia->getVersion());
    }

    public function testItResolvesClosureVersion(): void
    {
        $inertia = new Inertia();
        $inertia->version(fn () => 'resolved');

        $this->assertSame('resolved', $inertia->getVersion());
    }

    public function testItCreatesLazyProp(): void
    {
        $inertia = new Inertia();

        $this->assertInstanceOf(LazyProp::class, $inertia->lazy(fn () => 'value'));
    }

    public function testItRendersComponentMergingSharedProps(): void
    {
        $inertia = new Inertia();
        $inertia->share('shared', 'yes');
        $inertia->version('v1');

        $response = $inertia->render('Dashboard', ['local' => 'no']);

        $this->assertInstanceOf(InertiaResponse::class, $response);
        $this->assertSame('Dashboard', $response->component);
        $this->assertSame('v1', $response->version);
        $this->assertSame('inertia', $response->rootView);
    }
}
