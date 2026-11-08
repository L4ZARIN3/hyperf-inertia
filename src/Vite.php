<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia;

use Hyperf\Macroable\Macroable;
use Hyperf\Stringable\Str;
use Hyperf\ViewEngine\Contract\Htmlable;
use Hyperf\ViewEngine\HtmlString;

use function Hyperf\Collection\collect;
use function Hyperf\Config\config;

class Vite implements Htmlable
{
    use Macroable;

    /**
     * The Content Security Policy nonce to apply to all generated tags.
     */
    protected ?string $nonce = null;

    /**
     * The key to check for integrity hashes within the manifest.
     */
    protected bool|string $integrityKey = 'integrity';

    /**
     * The configured entry points.
     */
    protected array $entryPoints = [];

    /**
     * The path to the "hot" file.
     */
    protected ?string $hotFile = null;

    /**
     * The path to the build directory.
     */
    protected string $buildDirectory = 'build';

    /**
     * The name of the manifest file.
     */
    protected string $manifestFilename = 'manifest.json';

    /**
     * The script tag attributes resolvers.
     */
    protected array $scriptTagAttributesResolvers = [];

    /**
     * The style tag attributes resolvers.
     */
    protected array $styleTagAttributesResolvers = [];

    /**
     * The preload tag attributes resolvers.
     */
    protected array $preloadTagAttributesResolvers = [];

    /**
     * The preloaded assets.
     */
    protected array $preloadedAssets = [];

    /**
     * The cached manifest files.
     */
    protected static array $manifests = [];

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * Generate Vite tags for an entrypoint.
     *
     * @throws \Exception
     */
    public function __invoke(array|string $entrypoints, ?string $buildDirectory = null): HtmlString
    {
        $entrypoints = collect($entrypoints);
        $buildDirectory ??= $this->buildDirectory;

        if ($this->isRunningHot()) {
            return new HtmlString(
                $entrypoints
                    ->prepend('@vite/client')
                    ->map(fn ($entrypoint) => $this->makeTagForChunk(
                        $entrypoint,
                        $this->hotAsset($entrypoint),
                        null,
                        null
                    ))
                    ->implode('')
            );
        }

        $manifest = $this->manifest($buildDirectory);

        $tags = collect();
        $preloads = collect();

        foreach ($entrypoints as $entrypoint) {
            $chunk = $this->chunk($manifest, $entrypoint);

            $preloads->push([
                $chunk['src'],
                $this->assetPath("{$buildDirectory}/{$chunk['file']}"),
                $chunk,
                $manifest,
            ]);

            foreach ($chunk['imports'] ?? [] as $import) {
                $preloads->push([
                    $import,
                    $this->assetPath("{$buildDirectory}/{$manifest[$import]['file']}"),
                    $manifest[$import],
                    $manifest,
                ]);

                foreach ($manifest[$import]['css'] ?? [] as $css) {
                    [$cssSrc, $cssChunk] = $this->findCssChunk($manifest, $css);

                    $preloads->push([
                        $cssSrc,
                        $this->assetPath("{$buildDirectory}/{$css}"),
                        $cssChunk,
                        $manifest,
                    ]);

                    $tags->push($this->makeTagForChunk(
                        $cssSrc,
                        $this->assetPath("{$buildDirectory}/{$css}"),
                        $cssChunk,
                        $manifest
                    ));
                }
            }

            $tags->push($this->makeTagForChunk(
                $entrypoint,
                $this->assetPath("{$buildDirectory}/{$chunk['file']}"),
                $chunk,
                $manifest
            ));

            foreach ($chunk['css'] ?? [] as $css) {
                [$cssSrc, $cssChunk] = $this->findCssChunk($manifest, $css);

                $preloads->push([
                    $cssSrc,
                    $this->assetPath("{$buildDirectory}/{$css}"),
                    $cssChunk,
                    $manifest,
                ]);

                $tags->push($this->makeTagForChunk(
                    $cssSrc,
                    $this->assetPath("{$buildDirectory}/{$css}"),
                    $cssChunk,
                    $manifest
                ));
            }
        }

        [$stylesheets, $scripts] = $tags->unique()->partition(fn ($tag) => str_starts_with($tag, '<link'));

        $preloads = $preloads->unique()
            ->sortByDesc(fn ($args) => $this->isCssPath($args[1]))
            ->map(fn ($args) => $this->makePreloadTagForChunk(...$args));

        return new HtmlString($preloads->implode('') . $stylesheets->implode('') . $scripts->implode(''));
    }

    /**
     * Get the preloaded assets.
     */
    public function preloadedAssets(): array
    {
        return $this->preloadedAssets;
    }

    /**
     * Get the Content Security Policy nonce applied to all generated tags.
     */
    public function cspNonce(): ?string
    {
        return $this->nonce;
    }

    /**
     * Generate or set a Content Security Policy nonce to apply to all generated tags.
     */
    public function useCspNonce(?string $nonce = null): string
    {
        return $this->nonce = $nonce ?? Str::random(40);
    }

    /**
     * Use the given key to detect integrity hashes in the manifest.
     */
    public function useIntegrityKey(bool|string $key): static
    {
        $this->integrityKey = $key;

        return $this;
    }

    /**
     * Set the Vite entry points.
     */
    public function withEntryPoints(array $entryPoints): static
    {
        $this->entryPoints = $entryPoints;

        return $this;
    }

    /**
     * Set the filename for the manifest file.
     */
    public function useManifestFilename(string $filename): static
    {
        $this->manifestFilename = $filename;

        return $this;
    }

    /**
     * Get the Vite "hot" file path.
     */
    public function hotFile(): string
    {
        return $this->hotFile ?? self::getPublicPath('hot');
    }

    /**
     * Set the Vite "hot" file path.
     */
    public function useHotFile(string $path): static
    {
        $this->hotFile = $path;

        return $this;
    }

    /**
     * Set the Vite build directory.
     */
    public function useBuildDirectory(string $path): static
    {
        $this->buildDirectory = $path;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for script tags.
     *
     * @param array|(callable(string, string, ?array, ?array): array) $attributes
     */
    public function useScriptTagAttributes($attributes): static
    {
        if (! is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->scriptTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for style tags.
     *
     * @param array|(callable(string, string, ?array, ?array): array) $attributes
     */
    public function useStyleTagAttributes($attributes): static
    {
        if (! is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->styleTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for preload tags.
     *
     * @param array|(callable(string, string, ?array, ?array): (array|false))|false $attributes
     */
    public function usePreloadTagAttributes($attributes): static
    {
        if (! is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->preloadTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Generate React refresh runtime script.
     */
    public function reactRefresh(): ?HtmlString
    {
        if (! $this->isRunningHot()) {
            return null;
        }

        $attributes = $this->parseAttributes([
            'nonce' => $this->cspNonce(),
        ]);

        return new HtmlString(
            sprintf(
                <<<'HTML'
                <script type="module" %s>
                    import RefreshRuntime from '%s'
                    RefreshRuntime.injectIntoGlobalHook(window)
                    window.$RefreshReg$ = () => {}
                    window.$RefreshSig$ = () => (type) => type
                    window.__vite_plugin_react_preamble_installed__ = true
                </script>
                HTML,
                implode(' ', $attributes),
                $this->hotAsset('@react-refresh')
            )
        );
    }

    /**
     * Get the URL for an asset.
     */
    public function asset(string $asset, ?string $buildDirectory = null): string
    {
        $buildDirectory ??= $this->buildDirectory;

        if ($this->isRunningHot()) {
            return $this->hotAsset($asset);
        }

        $chunk = $this->chunk($this->manifest($buildDirectory), $asset);

        return $this->assetPath($buildDirectory . '/' . $chunk['file']);
    }

    /**
     * Get the content of a given asset.
     * @throws \Exception
     */
    public function content(string $asset, ?string $buildDirectory = null): string
    {
        $buildDirectory ??= $this->buildDirectory;
        $chunk = $this->chunk($this->manifest($buildDirectory), $asset);
        $path = self::getPublicPath($buildDirectory . '/' . $chunk['file']);

        if (! is_file($path) || ! file_exists($path)) {
            throw new \Exception("Unable to locate file from Vite manifest: {$path}.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \Exception("Unable to read file from Vite manifest: {$path}.");
        }

        return $contents;
    }

    /**
     * Get a unique hash representing the current manifest, or null if there is no manifest.
     */
    public function manifestHash(?string $buildDirectory = null): ?string
    {
        $buildDirectory ??= $this->buildDirectory;

        if ($this->isRunningHot()) {
            return null;
        }

        if (! is_file($path = $this->manifestPath($buildDirectory))) {
            return null;
        }

        return md5_file($path) ?: null;
    }

    public static function getPublicPath(string $path): string
    {
        return config('inertia.public_path_prefix') . (
            $path != ''
                ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR)
                : ''
        );
    }

    /**
     * Determine if the HMR server is running.
     */
    public function isRunningHot(): bool
    {
        return is_file($this->hotFile());
    }

    /**
     * Get the Vite tag content as a string of HTML.
     */
    public function toHtml(): string
    {
        return $this->__invoke($this->entryPoints)->toHtml();
    }

    /**
     * Make tag for the given chunk.
     */
    protected function makeTagForChunk(string $src, string $url, ?array $chunk, ?array $manifest): string
    {
        if (
            $this->nonce === null
            && $this->integrityKey !== false
            && ! array_key_exists($this->integrityKey, $chunk ?? [])
            && $this->scriptTagAttributesResolvers === []
            && $this->styleTagAttributesResolvers === []) {
            return $this->makeTag($url);
        }

        if ($this->isCssPath($url)) {
            return $this->makeStylesheetTagWithAttributes(
                $url,
                $this->resolveStylesheetTagAttributes($src, $url, $chunk, $manifest)
            );
        }

        return $this->makeScriptTagWithAttributes(
            $url,
            $this->resolveScriptTagAttributes($src, $url, $chunk, $manifest)
        );
    }

    /**
     * Make a preload tag for the given chunk.
     */
    protected function makePreloadTagForChunk(string $src, string $url, array $chunk, array $manifest): string
    {
        $attributes = $this->resolvePreloadTagAttributes($src, $url, $chunk, $manifest);

        if ($attributes === false) {
            return '';
        }

        $this->preloadedAssets[$url] = $this->parseAttributes(
            collect($attributes)->forget('href')->all()
        );

        return '<link ' . implode(' ', $this->parseAttributes($attributes)) . ' />';
    }

    /**
     * Resolve the attributes for the chunks generated script tag.
     */
    protected function resolveScriptTagAttributes(
        string $src,
        string $url,
        ?array $chunk,
        ?array $manifest
    ): array {
        $attributes = $this->integrityKey !== false
            ? ['integrity' => $chunk[$this->integrityKey] ?? false]
            : [];

        foreach ($this->scriptTagAttributesResolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }

        return $attributes;
    }

    /**
     * Resolve the attributes for the chunks generated stylesheet tag.
     */
    protected function resolveStylesheetTagAttributes(
        string $src,
        string $url,
        ?array $chunk,
        ?array $manifest
    ): array {
        $attributes = $this->integrityKey !== false
            ? ['integrity' => $chunk[$this->integrityKey] ?? false]
            : [];

        foreach ($this->styleTagAttributesResolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }

        return $attributes;
    }

    /**
     * Resolve the attributes for the chunks generated preload tag.
     */
    protected function resolvePreloadTagAttributes(string $src, string $url, array $chunk, array $manifest): array|bool
    {
        $attributes = $this->isCssPath($url) ? [
            'rel' => 'preload',
            'as' => 'style',
            'href' => $url,
            'nonce' => $this->nonce ?? false,
            'crossorigin' => $this->resolveStylesheetTagAttributes($src, $url, $chunk, $manifest)['crossorigin'] ?? false,
        ] : [
            'rel' => 'modulepreload',
            'href' => $url,
            'nonce' => $this->nonce ?? false,
            'crossorigin' => $this->resolveScriptTagAttributes($src, $url, $chunk, $manifest)['crossorigin'] ?? false,
        ];

        $attributes = $this->integrityKey !== false
            ? array_merge($attributes, ['integrity' => $chunk[$this->integrityKey] ?? false])
            : $attributes;

        foreach ($this->preloadTagAttributesResolvers as $resolver) {
            if (false === ($resolvedAttributes = $resolver($src, $url, $chunk, $manifest))) {
                return false;
            }

            $attributes = array_merge($attributes, $resolvedAttributes);
        }

        return $attributes;
    }

    /**
     * Generate an appropriate tag for the given URL in HMR mode.
     *
     * @deprecated will be removed in a future Laravel version
     */
    protected function makeTag(string $url): string
    {
        if ($this->isCssPath($url)) {
            return $this->makeStylesheetTagWithAttributes($url, []);
        }

        return $this->makeScriptTagWithAttributes($url, []);
    }

    /**
     * Generate a script tag with attributes for the given URL.
     */
    protected function makeScriptTagWithAttributes(string $url, array $attributes): string
    {
        $attributes = $this->parseAttributes(array_merge([
            'type' => 'module',
            'src' => $url,
            'nonce' => $this->nonce ?? false,
        ], $attributes));

        return '<script ' . implode(' ', $attributes) . '></script>';
    }

    /**
     * Generate a link tag with attributes for the given URL.
     */
    protected function makeStylesheetTagWithAttributes(string $url, array $attributes): string
    {
        $attributes = $this->parseAttributes(array_merge([
            'rel' => 'stylesheet',
            'href' => $url,
            'nonce' => $this->nonce ?? false,
        ], $attributes));

        return '<link ' . implode(' ', $attributes) . ' />';
    }

    /**
     * Determine whether the given path is a CSS file.
     */
    protected function isCssPath(string $path): bool
    {
        return preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)$/', $path) === 1;
    }

    /**
     * Parse the attributes into key="value" strings.
     */
    protected function parseAttributes(array $attributes): array
    {
        return collect($attributes)
            ->reject(fn ($value, $key) => in_array($value, [false, null], true))
            ->flatMap(fn ($value, $key) => $value === true ? [$key] : [$key => $value])
            ->map(fn ($value, $key) => is_int($key) ? $value : $key . '="' . $value . '"')
            ->values()
            ->all();
    }

    /**
     * Get the path to a given asset when running in HMR mode.
     * @param mixed $asset
     */
    protected function hotAsset($asset): string
    {
        $hot = file_get_contents($this->hotFile());

        if ($hot === false) {
            throw new \RuntimeException("Unable to read Vite hot file at: {$this->hotFile()}");
        }

        return rtrim($hot) . '/' . $asset;
    }

    /**
     * Generate an asset path for the application.
     */
    protected function assetPath(string $path): string
    {
        return config('inertia.asset_public_path') . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the the manifest file for the given build directory.
     *
     * The manifest is cached per process, but the cache key includes the file
     * modification time so that a new `vite build` is picked up without having
     * to restart the server.
     *
     * @throws ViteManifestNotFoundException
     */
    protected function manifest(string $buildDirectory): array
    {
        $path = $this->manifestPath($buildDirectory);

        if (! is_file($path)) {
            throw new ViteManifestNotFoundException("Vite manifest not found at: {$path}");
        }

        $mtime = filemtime($path);
        $key = $path . '@' . ($mtime === false ? '0' : (string) $mtime);

        if (! isset(static::$manifests[$key])) {
            $manifest = file_get_contents($path);

            if ($manifest === false) {
                throw new ViteManifestNotFoundException("Unable to read Vite manifest at: {$path}");
            }

            $decoded = json_decode($manifest, true);

            if (! is_array($decoded)) {
                throw new ViteManifestNotFoundException("Unable to decode Vite manifest at: {$path}");
            }

            static::$manifests[$key] = $decoded;
        }

        return static::$manifests[$key];
    }

    /**
     * Get the path to the manifest file for the given build directory.
     *
     * Vite 6 writes the manifest to `build/.vite/manifest.json` by default,
     * while earlier versions and the Laravel plugin use `build/manifest.json`.
     * We look in both locations.
     */
    protected function manifestPath(string $buildDirectory): string
    {
        $candidates = [
            $buildDirectory . '/' . $this->manifestFilename,
            $buildDirectory . '/.vite/' . $this->manifestFilename,
        ];

        foreach ($candidates as $candidate) {
            $path = self::getPublicPath($candidate);
            if (is_file($path)) {
                return $path;
            }
        }

        return self::getPublicPath($candidates[0]);
    }

    /**
     * Get the chunk for the given entry point / asset.
     *
     * @throws \Exception
     */
    protected function chunk(array $manifest, string $file): array
    {
        if (! isset($manifest[$file])) {
            throw new \Exception("Unable to locate file in Vite manifest: {$file}.");
        }

        return $manifest[$file];
    }

    /**
     * Locate the manifest entry for a CSS asset.
     *
     * Vite 6 lists CSS files on a chunk's `css` array without necessarily
     * exposing a standalone manifest entry for them, so we tolerate a missing
     * entry by returning an empty chunk.
     *
     * @return array{0: string, 1: array}
     */
    protected function findCssChunk(array $manifest, string $css): array
    {
        foreach ($manifest as $src => $chunk) {
            if (($chunk['file'] ?? null) === $css) {
                return [(string) $src, $chunk];
            }
        }

        return [$css, []];
    }
}
