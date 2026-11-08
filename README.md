# Inertia.js Hyperf Adapter

> 🍴 **Este repositório é um fork de [onix-systems-php/hyperf-inertia](https://github.com/onix-systems-php/hyperf-inertia)**,
> modernizado para **Hyperf 3.2 / PHP 8.2+** com correções de bugs, suporte ao Vite 6,
> macros de teste funcionais (`assertInertia`), testes e documentação completa.

The Hyperf adapter for [Inertia.js](https://inertiajs.com/), letting you build
single-page applications using classic server-side routing and controllers.

> ⚡ **Começando agora?** [QUICKSTART.md](QUICKSTART.md) — da instalação à
> primeira página em 10 passos rápidos.
>
> 📖 **Documentação completa (instalação, render, shared/lazy props, validação,
> redirects, Vite, SSR, testes e referência):** [DOCUMENTACAO.md](DOCUMENTACAO.md)

## Requirements

- PHP `>= 8.2`
- [Hyperf](https://hyperf.io) `3.2`
- Node.js `>= 18` and npm (for the front-end tooling)

## Installation

1. Require the package:

    ```bash
    composer require onix-systems-php/hyperf-inertia
    ```

2. Publish the config and the middleware:

    ```bash
    php ./bin/hyperf.php vendor:publish onix-systems-php/hyperf-inertia
    ```

3. Initialize the front-end part (creates `package.json` entries and scaffolds
   the `storage/inertia` + `storage/view/layouts` structure):

    ```bash
    php ./bin/hyperf.php inertia:init
    ```

4. Add the dev/build scripts to your `package.json`:

    ```json
    {
      "scripts": {
        "dev": "vite",
        "build": "vite build",
        "build-ssr": "vite build && vite build --ssr"
      }
    }
    ```

5. Install the Node dependencies and start Vite:

    ```bash
    npm install
    npm run dev        # development
    npm run build      # production
    ```

> **Note** If you use Svelte, your `package.json` must declare
> `"type": "module"`.

## Middleware

Register the published middleware in `./config/middlewares.php`. It must be
placed **before** the `validation` middleware so that validation errors can be
captured and shared as Inertia props.

```php
'http' => [
    \App\Common\Middleware\HandleInertiaMiddleware::class,
],
```

Customize the shared props (for example the authenticated user or flash
messages) by overriding `share()` in the published middleware.

## Creating responses

```php
#[GetMapping(path: '/test-feedback')]
public function test(RequestInterface $request)
{
    return inertia('Test', [
        'title' => 'Feedback form',
        'formTitle' => "What's on your mind?",
    ])->toResponse($request);
}
```

```php
#[PostMapping('/test-feedback')]
public function testFeedback(RequestInterface $request)
{
    $validator = $this->validationFactory->make($request->all(), [
        'fullname' => 'required|string|min:3|max:255',
        'email' => 'required|email',
        'message' => 'required|string|min:10|max:255',
    ]);
    $validator->validate();

    return redirect_with('test-feedback', [
        'alert' => [
            'message' => 'Feedback was successfully sent',
            'type' => 'success',
        ],
    ]);
}
```

### Shared, lazy and partial data

```php
$inertia = inertia();

// Share data with every response.
$inertia->share('appName', 'My App');

// Lazy props are only evaluated for partial reloads that request them.
$inertia->render('Dashboard', [
    'stats' => $inertia->lazy(fn () => $this->stats()),
]);
```

## Frontend part

After running `php ./bin/hyperf.php inertia:init` you get the following layout:

```
.
├── storage
│   ├── inertia
│   │   ├── css
│   │   │   └── app.css
│   │   └── js
│   │       ├── Components
│   │       ├── Layouts
│   │       ├── Pages
│   │       ├── app.js
│   │       ├── ssr.js (optional)
│   │       └── bootstrap.js
│   ├── view
│   │   └── layouts
│   │       └── inertia.blade.php
│   └── ...
├── vite.config.js
└── ...
```

Create your own components, layouts and pages inside `Components`, `Layouts`
and `Pages`.

## Server-side rendering

In the `inertia:init` process you can enable SSR. The `ssr` file is also
available at `src/Commands/Stubs/{vue|react|svelte}/ssr.{js,jsx}`.

- `npm run build-ssr`
- `php ./bin/hyperf.php inertia:start-ssr` (run it inside your container / server)
- `php ./bin/hyperf.php inertia:stop-ssr`

## Environment variables

| Variable                   | Default                     | Description |
| -------------------------- | --------------------------- | ----------- |
| `INERTIA_SSR_ENABLED`      | `false`                     | Enable server-side rendering. |
| `INERTIA_SSR_URL`          | `http://127.0.0.1:13714`    | URL of the SSR server. |
| `INERTIA_IS_SECURE`        | `true`                      | Set to `false` when serving over HTTP. |
| `INERTIA_SKIP_URL_PREFIX`  | (empty)                     | Comma-separated URL prefixes skipped by Inertia (e.g. `v1,api`). |
| `INERTIA_NO_SKIP_EXTRA_PATH` | (empty)                   | Comma-separated paths that are still handled even when their prefix is skipped (e.g. `admin/login`). |

## Testing

The package ships with Inertia assertions for Hyperf tests:

```php
$this->get('/test-feedback')
    ->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Test')
        ->has('title')
        ->where('title', 'Feedback form'));
```

## Development

```bash
composer test       # run the test suite
composer analyse    # run PHPStan
composer cs-fix     # fix code style
```

## License

MIT. See [LICENSE](LICENSE).
