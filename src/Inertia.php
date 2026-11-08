<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia;

use Hyperf\Collection\Arr;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\Arrayable;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpMessage\Uri\Uri;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Macroable\Macroable;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

use function Hyperf\Config\config;
use function Hyperf\Support\call;

class Inertia
{
    use Macroable;

    protected string $rootView = 'app';

    /** @var array<string, mixed> */
    protected array $sharedProps = [];

    protected \Closure|string|null $version = null;

    public function __construct()
    {
        $this->rootView = (string) config('inertia.root_view', $this->rootView);
    }

    public function setRootView(string $name): static
    {
        $this->rootView = $name;

        return $this;
    }

    public function share(array|Arrayable|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } elseif ($key instanceof Arrayable) {
            $this->sharedProps = array_merge($this->sharedProps, $key->toArray());
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }

        return $this;
    }

    public function getShared(?string $key = null, mixed $default = null): mixed
    {
        if ($key !== null) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }

    public function flushShared(): static
    {
        $this->sharedProps = [];

        return $this;
    }

    public function version(\Closure|string|null $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getVersion(): string
    {
        $version = $this->version instanceof \Closure
            ? call($this->version)
            : $this->version;

        return (string) $version;
    }

    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    public function render(string $component, array|Arrayable $props = []): InertiaResponse
    {
        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        }

        return new InertiaResponse(
            $component,
            array_merge($this->sharedProps, $props),
            $this->rootView,
            $this->getVersion()
        );
    }

    public function location(string|Uri $url): PsrResponseInterface
    {
        $response = ApplicationContext::getContainer()->get(ResponseInterface::class);
        $request = ApplicationContext::getContainer()->get(RequestInterface::class);
        $url = $url instanceof Uri ? $url->toString() : $url;

        if ($request->hasHeader('X-Inertia')) {
            return $response
                ->withStatus(409)
                ->withAddedHeader('X-Inertia-Location', $url)
                ->withBody(new SwooleStream(''));
        }

        return $response->redirect(
            toUrl: $url,
            schema: $request->getUri()->getScheme()
        );
    }
}
