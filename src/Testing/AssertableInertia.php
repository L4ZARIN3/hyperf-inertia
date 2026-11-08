<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Testing;

use Hyperf\Testing\Fluent\AssertableJson;
use Hyperf\Testing\Http\TestResponse;
use Hyperf\ViewEngine\Finder;
use PHPUnit\Framework\Assert as PHPUnit;

use function Hyperf\Config\config;
use function Hyperf\Support\make;

class AssertableInertia extends AssertableJson
{
    private string $component;

    private string $url;

    private ?string $version;

    public static function fromTestResponse(TestResponse $response): self
    {
        $page = self::extractPage($response);

        $instance = static::fromArray($page['props']);
        $instance->component = $page['component'];
        $instance->url = $page['url'];
        $instance->version = $page['version'];

        return $instance;
    }

    public function component(?string $value = null, $shouldExist = null): self
    {
        PHPUnit::assertSame($value, $this->component, 'Unexpected Inertia page component.');

        if ($shouldExist || (is_null($shouldExist) && config('inertia.testing.ensure_pages_exist', true))) {
            try {
                $fileViewFinder = make(Finder::class);
                $fileViewFinder->find($value);
            } catch (\InvalidArgumentException $exception) {
                PHPUnit::fail(sprintf('Inertia page component file [%s] does not exist.', $value));
            }
        }

        return $this;
    }

    public function url(string $value): self
    {
        PHPUnit::assertSame($value, $this->url, 'Unexpected Inertia page url.');

        return $this;
    }

    public function version(string $value): self
    {
        PHPUnit::assertSame($value, $this->version, 'Unexpected Inertia asset version.');

        return $this;
    }

    public function toArray(): array
    {
        return [
            'component' => $this->component,
            'props' => $this->prop(),
            'url' => $this->url,
            'version' => $this->version,
        ];
    }

    /**
     * Extract the Inertia page from a Hyperf test response.
     *
     * An Inertia response is either a JSON payload (for requests carrying the
     * `X-Inertia` header) or an HTML document embedding the page in the
     * `data-page` attribute of the root element (first visits).
     *
     * @return array{component:string, props:array, url:string, version:?string}
     */
    private static function extractPage(TestResponse $response): array
    {
        $content = $response->getContent();

        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['component'], $decoded['props'])) {
            return $decoded;
        }

        if (preg_match('/data-page="([^"]*)"/', $content, $matches) === 1) {
            $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
            if (is_array($decoded) && isset($decoded['component'], $decoded['props'])) {
                return $decoded;
            }
        }

        PHPUnit::fail('Not a valid Inertia response.');
    }
}
