<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */

namespace OnixSystemsPHP\HyperfInertia\Commands;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Stringable\Str;
use Hyperf\Support\Filesystem\Filesystem;
use OnixSystemsPHP\HyperfInertia\Enum\StackEnum;
use Symfony\Component\Console\Question\ChoiceQuestion;

#[Command]
class InertiaInitCommand extends HyperfCommand
{
    public const INERTIA_PATH = '/storage/inertia';

    protected array $nodePackages = [
        'core' => [
            'autoprefixer' => '^10.4',
            'axios' => '^1.7',
        ],
        'vue' => [
            'default' => [
                'vue' => '^3.5',
                '@inertiajs/vue3' => '^2.0',
                '@vitejs/plugin-vue' => '^5.2',
                'vite' => '^6.0',
            ],
            'ssr' => ['@vue/server-renderer' => '^3.5'],
        ],
        'react' => [
            'default' => [
                '@inertiajs/react' => '^2.0',
                '@vitejs/plugin-react' => '^4.4',
                'react' => '^18.3',
                'react-dom' => '^18.3',
                'vite' => '^6.0',
            ],
            'ssr' => [],
        ],
        'svelte' => [
            'default' => [
                'svelte' => '^5.0',
                '@inertiajs/svelte' => '^2.0',
                '@sveltejs/vite-plugin-svelte' => '^5.0',
                'vite' => '^6.0',
            ],
            'ssr' => [],
        ],
    ];

    private bool $hasSsr = false;

    private ?StackEnum $stack = null;

    public function __construct(private readonly Filesystem $filesystem)
    {
        parent::__construct('inertia:init');
    }

    public function configure(): void
    {
        parent::configure();
        $this->setDescription('Install the Inertia components and resources');
    }

    public function handle(): void
    {
        $this->makeStructure();
        $stackQuestion = new ChoiceQuestion('Select the development stack', array_column(StackEnum::cases(), 'value'));
        $stack = $this->getHelper('question')->ask($this->input, $this->output, $stackQuestion);

        $ssrQuestion = new ChoiceQuestion('Do you want to enable server side rendering?', ['yes', 'no'], 1);
        $hasSrr = $this->getHelper('question')->ask($this->input, $this->output, $ssrQuestion);
        $this->hasSsr = $hasSrr === 'yes';

        $stack = StackEnum::tryFrom($stack);
        $this->stack = $stack;
        $path = $this->getStubPath($stack);
        $resources = $this->getResources($stack);
        $this->copyResources($path, $resources);

        $nodePackages = $this->getNodePackages($stack);
        $this->updateNodePackages(function ($packages) use ($nodePackages) {
            return [...$packages, ...$nodePackages];
        });
    }

    protected function updateNodePackages(callable $callback, $dev = true): void
    {
        $packagePath = BASE_PATH . '/package.json';

        if (! $this->filesystem->exists($packagePath)) {
            $packages = $this->defaultPackageJson();
            $this->info('The package.json file was not found, so a new one was created.');
        } else {
            $packages = json_decode(file_get_contents($packagePath), true);
        }

        $configurationKey = $dev ? 'devDependencies' : 'dependencies';

        $packages[$configurationKey] = $callback(
            array_key_exists($configurationKey, $packages) ? $packages[$configurationKey] : [],
            $configurationKey
        );

        ksort($packages[$configurationKey]);

        $this->ensureScripts($packages);

        file_put_contents(
            $packagePath,
            json_encode($packages, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
        );
    }

    protected function ensureScripts(array &$packages): void
    {
        $defaults = $this->defaultPackageJson()['scripts'];
        $scripts = $packages['scripts'] ?? [];

        foreach ($defaults as $name => $command) {
            $scripts[$name] ??= $command;
        }

        // Replace the old Laravel-plugin based SSR command, which no longer
        // writes the bundle where the backend expects it (bootstrap/ssr).
        if (($scripts['build-ssr'] ?? null) === 'vite build && vite build --ssr') {
            $scripts['build-ssr'] = $defaults['build-ssr'];
        }

        if ($scripts !== []) {
            $packages['scripts'] = $scripts;
        }
    }

    protected function defaultPackageJson(): array
    {
        $ssrEntry = $this->stack === StackEnum::REACT
            ? 'storage/inertia/js/ssr.jsx'
            : 'storage/inertia/js/ssr.js';

        return [
            'private' => true,
            'type' => 'module',
            'scripts' => [
                'dev' => 'vite',
                'build' => 'vite build',
                'build-ssr' => 'vite build && vite build --ssr ' . $ssrEntry . ' --outDir bootstrap/ssr',
            ],
        ];
    }

    private function makeStructure(): void
    {
        if (! $this->filesystem->exists(self::getInertiaPath())) {
            $this->filesystem->makeDirectory(path: self::getInertiaPath(), recursive: true);
        }

        $this->filesystem->makeDirectory(path: self::getInertiaPath() . '/js/Components', recursive: true);
        $this->filesystem->makeDirectory(path: self::getInertiaPath() . '/js/Pages', recursive: true);
        $this->filesystem->makeDirectory(path: self::getInertiaPath() . '/js/Layouts', recursive: true);
        $this->filesystem->makeDirectory(path: self::getInertiaPath() . '/css', recursive: true);
    }

    private function getResources(StackEnum $stack): array
    {
        $resources = [
            'vite' => 'vite.config.js',
            'app' => 'inertia.blade.php',
            'css' => 'app.css',
            'bootstrap' => 'bootstrap.js',
        ];

        $additional = match ($stack) {
            StackEnum::VUE => ['index' => 'app.js', 'ssr' => 'ssr.js'],
            StackEnum::REACT => ['index' => 'app.jsx', 'ssr' => 'ssr.jsx'],
            StackEnum::SVELTE => ['index' => 'app.js', 'ssr' => 'ssr.js'],
        };

        return [...$resources, ...$additional];
    }

    private function getNodePackages(StackEnum $stack): array
    {
        $nodePackages = [
            ...$this->nodePackages['core'],
            ...$this->nodePackages[$stack->value]['default'],
        ];

        if ($this->hasSsr) {
            $nodePackages = [...$nodePackages, ...$this->nodePackages[$stack->value]['ssr']];
        }

        return $nodePackages;
    }

    private function getStubPath(StackEnum $stack): string
    {
        return __DIR__ . '/Stubs/' . Str::ucfirst($stack->value) . '/';
    }

    private function copyResources(string $path, array $resources): void
    {
        $this->copyResource($path, BASE_PATH . '/storage/view/layouts/', $resources['app']);
        $this->copyResource($path, BASE_PATH . '/', $resources['vite']);
        $this->copyResource($path, self::getInertiaPath() . '/js/', $resources['bootstrap']);
        $this->copyResource($path, self::getInertiaPath() . '/js/', $resources['index']);
        $this->copyResource($path, self::getInertiaPath() . '/css/', $resources['css']);

        if ($this->hasSsr) {
            $this->copyResource($path, self::getInertiaPath() . '/js/', $resources['ssr']);
        }
    }

    private function copyResource(string $path, string $target, string $fileName): void
    {
        if (! $this->filesystem->exists($target)) {
            $this->filesystem->makeDirectory($target, recursive: true);
        }

        if ($this->filesystem->copy($path . $fileName, $target . $fileName)) {
            $this->info(sprintf('%s Was copied', $target . $fileName));
        }
    }

    private static function getInertiaPath(): string
    {
        return BASE_PATH . self::INERTIA_PATH;
    }
}
