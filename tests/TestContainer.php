<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Test;

use Psr\Container\ContainerInterface;

final class TestContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $bindings = [];

    public function set(string $id, mixed $value): void
    {
        $this->bindings[$id] = $value;
    }

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new \RuntimeException("No binding found for: {$id}");
        }

        return $this->bindings[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings);
    }
}
