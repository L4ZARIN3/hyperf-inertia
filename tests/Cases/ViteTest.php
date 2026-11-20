<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Test\Cases;

use OnixSystemsPHP\HyperfInertia\Vite;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(Vite::class)]
class ViteTest extends AbstractTestCase
{
    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->hotFile)) {
            unlink($this->hotFile);
        }

        parent::tearDown();
    }

    public function testItGeneratesCspNonce(): void
    {
        $vite = new Vite();
        $nonce = $vite->useCspNonce();

        $this->assertNotEmpty($nonce);
        $this->assertSame($nonce, $vite->cspNonce());
    }

    public function testItAcceptsProvidedCspNonce(): void
    {
        $vite = new Vite();

        $this->assertSame('my-nonce', $vite->useCspNonce('my-nonce'));
        $this->assertSame('my-nonce', $vite->cspNonce());
    }

    public function testItDetectsHotModeAndRendersDevTags(): void
    {
        file_put_contents($this->hotFile, 'http://localhost:5173');

        $vite = new Vite();
        $vite->useHotFile($this->hotFile);
        $vite->withEntryPoints(['storage/inertia/js/app.js']);

        $html = $vite->toHtml();

        $this->assertStringContainsString('http://localhost:5173/@vite/client', $html);
        $this->assertStringContainsString('http://localhost:5173/storage/inertia/js/app.js', $html);
    }

    public function testItIsNotRunningHotWithoutHotFile(): void
    {
        $vite = new Vite();
        $vite->useHotFile('/nonexistent/path/hot');

        $this->assertFalse($vite->isRunningHot());
    }

    public function testManifestHashReturnsNullWhenMissing(): void
    {
        $vite = new Vite();
        $vite->useHotFile('/nonexistent/path/hot');
        $vite->useBuildDirectory('build');

        $this->assertNull($vite->manifestHash());
    }

    public function testGetPublicPath(): void
    {
        $this->assertSame(
            sys_get_temp_dir() . '/inertia-test-public' . DIRECTORY_SEPARATOR . 'build/manifest.json',
            Vite::getPublicPath('build/manifest.json')
        );
    }

    public function testManifestCacheInvalidatesAfterRebuild(): void
    {
        $dir = sys_get_temp_dir() . '/inertia-test-public/build';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $manifest = $dir . '/manifest.json';

        file_put_contents($manifest, json_encode([
            'storage/inertia/js/app.js' => ['file' => 'assets/app-old.js', 'src' => 'storage/inertia/js/app.js'],
        ]));
        clearstatcache(true, $manifest);

        $vite = new Vite();
        $vite->useHotFile('/nonexistent/path/hot');
        $vite->useBuildDirectory('build');

        $readManifest = fn () => (new \ReflectionMethod(Vite::class, 'manifest'))->invoke($vite, 'build');

        $this->assertSame('assets/app-old.js', $readManifest()['storage/inertia/js/app.js']['file']);

        // Simulate a new vite build: new content + new mtime.
        file_put_contents($manifest, json_encode([
            'storage/inertia/js/app.js' => ['file' => 'assets/app-new.js', 'src' => 'storage/inertia/js/app.js'],
        ]));
        touch($manifest, time() + 5);
        clearstatcache(true, $manifest);

        $this->assertSame('assets/app-new.js', $readManifest()['storage/inertia/js/app.js']['file']);

        unlink($manifest);
    }
}
