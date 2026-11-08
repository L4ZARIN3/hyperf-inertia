<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia;

use GuzzleHttp\Promise\PromiseInterface;
use Hyperf\Collection\Arr;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\Arrayable;
use Hyperf\Macroable\Macroable;
use Hyperf\Resource\Json\JsonResource;
use Hyperf\Resource\Response\Response;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function Hyperf\Support\call;

class InertiaResponse
{
    use Macroable;

    /** @var array<string, mixed> */
    public array $props;

    /** @var array<string, mixed> */
    protected array $viewData = [];

    public function __construct(
        public string $component,
        array|Arrayable $props,
        public string $rootView = 'app',
        public string $version = ''
    ) {
        $this->props = $props instanceof Arrayable ? $props->toArray() : $props;
    }

    public function with(array|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this;
    }

    public function withViewData(array|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    public function rootView(string $rootView): static
    {
        $this->rootView = $rootView;

        return $this;
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse(ServerRequestInterface $request): ResponseInterface
    {
        $only = array_filter(explode(',', $request->getHeaderLine('X-Inertia-Partial-Data')));

        $props = ($only && $request->getHeaderLine('X-Inertia-Partial-Component') === $this->component)
            ? Arr::only($this->props, $only)
            : array_filter($this->props, static fn ($prop) => ! $prop instanceof LazyProp);

        $props = $this->resolvePropertyInstances($props, $request);

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => $request->url(),
            'version' => $this->version,
        ];

        if ($request->hasHeader('X-Inertia')) {
            return (new Response(JsonResource::make($page)->withoutWrapping()))
                ->toResponse()
                ->addHeader('X-Inertia', 'true');
        }

        $render = ApplicationContext::getContainer()->get(RenderInterface::class);

        return $render->render('layouts/' . $this->rootView, $this->viewData + ['page' => $page]);
    }

    /**
     * Resolve all necessary class instances in the given props.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public function resolvePropertyInstances(
        array $props,
        ServerRequestInterface $request,
        bool $unpackDotProps = true
    ): array {
        foreach ($props as $key => $value) {
            if ($value instanceof \Closure) {
                $value = call($value);
            }

            if ($value instanceof LazyProp) {
                $value = call($value);
            }

            if ($value instanceof PromiseInterface) {
                $value = $value->wait();
            }

            if ($value instanceof JsonResource) {
                $value = $value->resolve();
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                $value = $this->resolvePropertyInstances($value, $request, false);
            }

            if ($unpackDotProps && str_contains($key, '.')) {
                Arr::set($props, $key, $value);
                unset($props[$key]);
            } else {
                $props[$key] = $value;
            }
        }

        return $props;
    }
}
