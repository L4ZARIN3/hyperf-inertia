<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Testing;

use Hyperf\Contract\Arrayable;
use Hyperf\Macroable\Macroable;
use PHPUnit\Framework\Assert as PHPUnit;

class Assert implements Arrayable
{
    use Concerns\Has;
    use Concerns\Matching;
    use Concerns\Debugging;
    use Concerns\PageObject;
    use Concerns\Interaction;
    use Macroable;

    /** @var string */
    private $component;

    /** @var array */
    private $props;

    /** @var string */
    private $url;

    /** @var null|string */
    private $version;

    /** @var string */
    private $path;

    protected function __construct(string $component, array $props, string $url, ?string $version = null, ?string $path = null)
    {
        echo "\033[0;31mInertia's built-in 'Assert' library will be removed in a future version of inertia-laravel:\033[0m\n";
        echo "\033[0;31m - If you are seeing this error while using \$response->assertInertia(...), please upgrade to Laravel 8.32.0 or higher.\033[0m\n";
        echo "\033[0;31m - If you are using the 'Assert' class directly, please adapt your tests to use the 'AssertableInertia' class instead.\033[0m\n";
        echo "\033[0;31mFor more information and questions, please see https://github.com/inertiajs/inertia-laravel/pull/338 \033[0m\n\n";
        @trigger_error("Inertia's built-in 'Assert' library will be removed in a future version of inertia-laravel: https://github.com/inertiajs/inertia-laravel/pull/338", \E_USER_DEPRECATED);

        $this->path = $path;

        $this->component = $component;
        $this->props = $props;
        $this->url = $url;
        $this->version = $version;
    }

    public static function fromTestResponse($response): self
    {
        $content = $response->getContent();

        $page = json_decode($content, true);
        if (! is_array($page) || ! isset($page['component'], $page['props'])) {
            $page = null;

            if (preg_match('/data-page="([^"]*)"/', $content, $matches) === 1) {
                $page = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
            }
        }

        if (! is_array($page) || ! isset($page['component'], $page['props'])) {
            PHPUnit::fail('Not a valid Inertia response.');
        }

        return new self($page['component'], $page['props'], $page['url'], $page['version']);
    }

    protected function dotPath(string $key): string
    {
        if (is_null($this->path)) {
            return $key;
        }

        return implode('.', [$this->path, $key]);
    }

    protected function scope(string $key, \Closure $callback): self
    {
        $props = $this->prop($key);
        $path = $this->dotPath($key);

        PHPUnit::assertIsArray($props, sprintf('Inertia property [%s] is not scopeable.', $path));

        $scope = new self($this->component, $props, $this->url, $this->version, $path);
        $callback($scope);
        $scope->interacted();

        return $this;
    }
}
