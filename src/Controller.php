<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia;

use Hyperf\Context\Context;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ServerRequestInterface;

class Controller
{
    public function __invoke(ServerRequestInterface $request): InertiaResponse
    {
        /** @var Inertia $inertia */
        $inertia = Context::get(Inertia::class, new Inertia());

        /** @var null|Dispatched $dispatched */
        $dispatched = $request->getAttribute(Dispatched::class);
        $defaults = $dispatched?->handler?->options['defaults'] ?? [];

        return $inertia->render(
            (string) ($defaults['component'] ?? ''),
            (array) ($defaults['props'] ?? [])
        );
    }
}
