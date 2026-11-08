<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Test\Cases;

use Hyperf\Contract\Arrayable;
use OnixSystemsPHP\HyperfInertia\InertiaResponse;
use OnixSystemsPHP\HyperfInertia\LazyProp;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
#[CoversClass(InertiaResponse::class)]
class InertiaResponseTest extends AbstractTestCase
{
    public function testItResolvesClosures(): void
    {
        $response = new InertiaResponse('Page', ['value' => fn () => 'resolved']);

        $props = $response->resolvePropertyInstances(['value' => fn () => 'resolved'], $this->request());

        $this->assertSame(['value' => 'resolved'], $props);
    }

    public function testItResolvesLazyProps(): void
    {
        $props = (new InertiaResponse('Page', []))->resolvePropertyInstances(
            ['value' => new LazyProp(fn () => 'lazy')],
            $this->request()
        );

        $this->assertSame(['value' => 'lazy'], $props);
    }

    public function testItResolvesArrayable(): void
    {
        $arrayable = new class implements Arrayable {
            public function toArray(): array
            {
                return ['a' => 1];
            }
        };

        $props = (new InertiaResponse('Page', []))->resolvePropertyInstances(
            ['value' => $arrayable],
            $this->request()
        );

        $this->assertSame(['value' => ['a' => 1]], $props);
    }

    public function testItResolvesNestedArraysRecursively(): void
    {
        $props = (new InertiaResponse('Page', []))->resolvePropertyInstances(
            ['value' => ['nested' => fn () => 'deep']],
            $this->request()
        );

        $this->assertSame(['value' => ['nested' => 'deep']], $props);
    }

    public function testItUnpacksTopLevelDotPropsOnly(): void
    {
        $props = (new InertiaResponse('Page', []))->resolvePropertyInstances(
            ['a.b' => 1, 'nested' => ['c.d' => 2]],
            $this->request()
        );

        $this->assertEquals(['a' => ['b' => 1], 'nested' => ['c.d' => 2]], $props);
    }

    public function testWithMergesProps(): void
    {
        $response = new InertiaResponse('Page', ['a' => 1]);

        $this->assertSame($response, $response->with(['b' => 2])->with('c', 3));

        $props = $response->resolvePropertyInstances($response->props, $this->request());
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $props);
    }

    private function request(): ServerRequestInterface
    {
        return $this->createMock(ServerRequestInterface::class);
    }
}
