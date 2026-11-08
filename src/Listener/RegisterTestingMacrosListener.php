<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Testing\Http\TestResponse;
use OnixSystemsPHP\HyperfInertia\Testing\AssertableInertia;

class RegisterTestingMacrosListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        if (! class_exists(TestResponse::class)) {
            return;
        }

        $response = TestResponse::class;

        if (! $response::hasMacro('assertInertia')) {
            $response::macro('assertInertia', function (?\Closure $callback = null) {
                $assert = AssertableInertia::fromTestResponse($this);

                if ($callback !== null) {
                    $callback($assert);
                }

                return $this;
            });
        }

        if (! $response::hasMacro('inertiaPage')) {
            $response::macro('inertiaPage', fn () => AssertableInertia::fromTestResponse($this)->toArray());
        }
    }
}
