# Hyperf Inertia — Documentação Completa

> Adapter oficial de [Inertia.js](https://inertiajs.com/) para **Hyperf 3.2**.
> Construa SPAs com Vue, React ou Svelte usando roteamento e controllers do lado do servidor — sem API JSON manual.

**Requisitos:** PHP ≥ 8.2 · Hyperf 3.2 · Node ≥ 18 · npm

**Exemplo completo rodando:** veja o site demo `pentest-site` (RaptorSec) com login, validação, lazy props e SSR-ready.

---

## Sumário

1. [Instalação em 5 passos](#1-instalação-em-5-passos)
2. [Sua primeira página](#2-sua-primeira-página)
3. [Criando respostas Inertia](#3-criando-respostas-inertia)
4. [Shared props (dados globais)](#4-shared-props-dados-globais)
5. [Lazy props e partial reloads](#5-lazy-props-e-partial-reloads)
6. [Redirects e mensagens flash](#6-redirects-e-mensagens-flash)
7. [Validação e erros](#7-validação-e-erros)
8. [`inertia_location()` — redirecionamento externo](#8-inertia_location--redirecionamento-externo)
9. [Versionamento de assets](#9-versionamento-de-assets)
10. [Vite: dev e produção](#10-vite-dev-e-produção)
11. [Server-Side Rendering (SSR)](#11-server-side-rendering-ssr)
12. [Testes com `assertInertia`](#12-testes-com-assertinertia)
13. [Componentes Blade](#13-componentes-blade)
14. [Comandos](#14-comandos)
15. [Referência de configuração](#15-referência-de-configuração)
16. [Variáveis de ambiente](#16-variáveis-de-ambiente)
17. [Vue, React ou Svelte](#17-vue-react-ou-svelte)
18. [Solução de problemas](#18-solução-de-problemas)

---

## 1. Instalação em 5 passos

```bash
# 1. Instale o pacote
composer require onix-systems-php/hyperf-inertia

# 2. Publique config e middleware
php ./bin/hyperf.php vendor:publish onix-systems-php/hyperf-inertia

# 3. Publique as configs de sessão e view (necessárias)
php ./bin/hyperf.php vendor:publish hyperf/session
php ./bin/hyperf.php vendor:publish hyperf/view-engine

# 4. Validação (para erros de formulário compartilhados)
composer require hyperf/validation

# 5. Inicialize o frontend (escolha vue, react ou svelte)
php ./bin/hyperf.php inertia:init
```

Depois, registre os middlewares em `config/autoload/middlewares.php`:

```php
return [
    'http' => [
        Hyperf\Session\Middleware\SessionMiddleware::class,
        App\Common\Middleware\HandleInertiaMiddleware::class,
    ],
];
```

> A sessão precisa estar **antes** do middleware Inertia. O middleware Inertia já
> captura `ValidationException` — por isso não é preciso registrar o middleware de validação.

---

## 2. Sua primeira página

### Backend (controller)

```php
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller]
class HomeController
{
    public function __construct(private RequestInterface $request) {}

    #[GetMapping(path: '/')]
    public function index()
    {
        return inertia('Home', [
            'title' => 'Minha primeira página Inertia',
        ])->toResponse($this->request);
    }
}
```

### Frontend (Vue)

`storage/inertia/js/Pages/Home.vue`

```vue
<script setup>
defineProps({ title: String });
</script>

<template>
  <div>
    <h1>{{ title }}</h1>
  </div>
</template>
```

`storage/inertia/js/app.js`

```js
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue');
    return pages[`./Pages/${name}.vue`]();
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) }).use(plugin).mount(el);
  },
});
```

### Root view (template Blade)

`storage/view/layouts/inertia.blade.php`

```blade
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title inertia>Meu App</title>
    @php $resource = ['storage/inertia/js/app.js']; @endphp
    <x-inertia-head :page="$page" :resource="$resource"/>
</head>
<body>
    <x-inertia-body :page="$page"/>
</body>
</html>
```

> O nome do componente (`Home`) vira o caminho do arquivo: `Home` → `Pages/Home.vue`,
> `Admin/Relatorios` → `Pages/Admin/Relatorios.vue`.

---

## 3. Criando respostas Inertia

### Helper `inertia()`

```php
// Com componente: retorna InertiaResponse (use ->toResponse())
return inertia('Dashboard', ['user' => $user])->toResponse($request);

// Sem componente: retorna a instância de Inertia (para share/lazy)
$inertia = inertia();
$inertia->share('appName', 'Meu App');
```

### Props de qualquer tipo

Props aceitam **arrays, objetos `Arrayable`, `JsonResource`, closures, Promises e `LazyProp`**:

```php
use Hyperf\Resource\Json\JsonResource;

return inertia('Users/Index', [
    'count' => fn () => $this->repo->count(),          // closure resolvida na resposta
    'user'  => new UserResource($user),                // JsonResource -> array
])->toResponse($request);
```

### `with()` e `withViewData()`

```php
inertia('Home')->with('extra', 'valor')->withViewData('title', 'Home');
```

### Root view customizada por resposta

```php
return inertia('Home')->rootView('blank')->toResponse($request);
```

### Rota genérica (opcional)

Use `OnixSystemsPHP\HyperfInertia\Controller` para servir o componente direto da rota,
sem criar um controller:

```php
use Hyperf\HttpServer\Router\Router;
use OnixSystemsPHP\HyperfInertia\Controller;

Router::get('/docs', Controller::class, [
    'defaults' => [
        'component' => 'Docs',
        'props' => ['version' => '1.0'],
    ],
]);
```

---

## 4. Shared props (dados globais)

Disponíveis em **todas** as respostas, sem repetir em cada controller.

No middleware publicado `app/Common/Middleware/HandleInertiaMiddleware.php`:

```php
public function share(ServerRequestInterface $request): array
{
    return array_merge(parent::share($request), [
        'appName' => 'RaptorSec',
        'auth' => [
            'user' => fn () => $this->users->findById((int) $this->session->get('user_id', 0)),
        ],
        'flash' => [
            'alert' => fn () => $this->session->get('alert'),
        ],
    ]);
}
```

No componente Vue:

```vue
<script setup>
import { usePage } from '@inertiajs/vue3';
const page = usePage();
</script>

<template>
  {{ page.props.appName }}          <!-- RaptorSec -->
  {{ page.props.auth.user?.name }}
</template>
```

Também é possível compartilhar no controller (escopo daquela request):

```php
$inertia = inertia();
$inertia->share('menu', $menu);          // array ou chave única
$inertia->share('user.name', 'André');   // notação de ponto
$inertia->getShared();                   // lê tudo
$inertia->getShared('user.name');        // lê uma chave
$inertia->flushShared();                 // limpa tudo
```

---

## 5. Lazy props e partial reloads

Props "lazy" só são avaliadas quando o cliente pede via **partial reload** — ótimo para
consultas caras que não devem rodar em toda navegação.

```php
return inertia('Dashboard', [
    'user' => $user,
    'metrics' => inertia()->lazy(fn () => $this->metrics->expensiveQuery()),
])->toResponse($request);
```

No frontend, peça apenas o que precisa:

```js
import { router } from '@inertiajs/vue3';

router.reload({
  only: ['metrics'],               // só esta prop vem no payload
});
```

| Comportamento | Sem partial | Com `only: ['metrics']` |
| --- | --- | --- |
| `user` | ✅ sempre presente | ✅ presente |
| `metrics` | ❌ ausente | ✅ avaliada |

> Na primeira visita (render completo) props lazy **não** são incluídas.

---

## 6. Redirects e mensagens flash

### `redirect_with()` — flash + redirect

```php
#[PostMapping('/contato')]
public function enviar(RequestInterface $request)
{
    // valida...

    return redirect_with('/contato', [
        'alert' => [
            'type' => 'success',
            'message' => 'Mensagem enviada!',
        ],
    ]);
}
```

Mostrando no layout:

```vue
<script setup>
import { usePage } from '@inertiajs/vue3';
const page = usePage();
</script>

<template>
  <div v-if="page.props.flash?.alert" :class="page.props.flash.alert.type">
    {{ page.props.flash.alert.message }}
  </div>
</template>
```

> O helper coloca os dados via `$session->flash(...)` — eles valem por **uma** request.

### Redirect simples

```php
use Hyperf\HttpServer\Contract\ResponseInterface;

return $this->response->redirect('/login');
```

---

## 7. Validação e erros

O middleware captura `ValidationException` **automaticamente**, guarda os erros na sessão
e redireciona de volta — os erros chegam como prop compartilhada `errors`.

```php
#[PostMapping('/login')]
public function login(RequestInterface $request)
{
    $validator = $this->validationFactory->make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string|min:6',
    ]);
    $validator->validate();   // lança ValidationException -> vira prop errors

    // credenciais inválidas:
    $validator->errors()->add('email', 'Credenciais inválidas.');
    throw new \Hyperf\Validation\ValidationException($validator);
}
```

No Vue (com `useForm`, os erros voltam direto no formulário):

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });
const submit = () => form.post('/login');
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="form.email" />
    <div v-if="form.errors.email">{{ form.errors.email }}</div>
    <input v-model="form.password" type="password" />
    <button :disabled="form.processing">Entrar</button>
  </form>
</template>
```

Lendo erros manualmente: `page.props.errors` (ex.: `errors.email`).

---

## 8. `inertia_location()` — redirecionamento externo

Força uma visita completa do navegador (útil para URLs externas ou páginas fora da SPA):

```php
#[GetMapping('/docs')]
public function docs()
{
    return inertia_location('https://inertiajs.com');
}
```

| Request | Resposta |
| --- | --- |
| Com header `X-Inertia` | `409` + header `X-Inertia-Location` (cliente segue) |
| Sem header | redirect `302` normal |

---

## 9. Versionamento de assets

O middleware calcula a versão automaticamente a partir do **manifest do Vite**
(suporta `build/manifest.json` **e** `build/.vite/manifest.json` do Vite 6) ou de `app.asset_url`.

- GET com `X-Inertia-Version` antiga → resposta `409` + `X-Inertia-Location`.
- O cliente Inertia recarrega a página inteira com os assets novos.

Para versionar manualmente, sobrescreva no seu middleware:

```php
public function version(ServerRequestInterface $request): ?string
{
    return parent::version($request) ?: (string) filemtime(BASE_PATH . '/storage/inertia/js/app.js');
}
```

---

## 10. Vite: dev e produção

### Estrutura mínima do `vite.config.js`

```js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  // SEM o base correto, os preloads/CSS dos chunks dinâmicos (carregados em
  // runtime pelo app.js) viram "/assets/..." e retornam 404.
  base: '/build/',
  build: {
    manifest: true,
    outDir: 'storage/public/build',
    emptyOutDir: true,
    rollupOptions: { input: 'storage/inertia/js/app.js' },
  },
});
```

> **Regra de ouro:** `base` do Vite + `asset_public_path` do Hyperf devem apontar
> para o mesmo lugar público. No exemplo: base = `/build/` e
> `asset_public_path = env('DOMAIN_API', '')` → URLs `/build/assets/...`.
> Se você mantiver o sufixo `/public` do config default, use `base: '/public/build/'`.

> **Após `npm run build`:** o pacote detecta o manifest novo automaticamente
> (cache por mtime — não precisa reiniciar o servidor). No navegador, recarregue
> com **Ctrl+Shift+R** uma vez para descartar o app.js antigo em cache.

### Como o pacote decide o modo

| Situação | Comportamento |
| --- | --- |
| Arquivo `storage/public/hot` existe (dev server do Vite) | tags apontam para o HMR (`@vite/client` + entrypoints) |
| Manifest `build/manifest.json` ou `build/.vite/manifest.json` existe | tags de produção (`<link>` + `<script>` com hash) |

```bash
npm run dev      # cria o arquivo hot -> modo HMR
npm run build    # gera storage/public/build/manifest.json -> produção
```

### Servindo os assets estáticos (importante!)

O roteador do Hyperf **não serve arquivos estáticos por padrão** — mas o
**Swoole por baixo dele serve**, com `enable_static_handler` + `document_root`.
Só atente ao alinhamento de prefixos: o default do pacote gera URLs com sufixo
`/public`; no exemplo abaixo removemos o sufixo para a URL bater com o
`document_root`.

**`config/autoload/server.php`** — settings do servidor http:

```php
'servers' => [
    [
        'name' => 'http',
        'type' => Server::SERVER_HTTP,
        'host' => '0.0.0.0',
        'port' => 9501,
        // ...
        'settings' => [
            'enable_static_handler' => true,
            'document_root' => BASE_PATH . '/storage/public',
        ],
    ],
],
```

**`config/autoload/inertia.php`** — remova o sufixo `/public` para as URLs
geradas baterem com o `document_root`:

```php
'asset_public_path' => env('DOMAIN_API', ''),
```

Resultado: o Swoole serve `/build/assets/app-xxx.js` direto de
`storage/public/build/` — com MIME automático e proteção própria contra
*path traversal* — sem nem tocar no roteador Hyperf.

> **Alternativa via PHP** (útil com motores sem static handler ou para
> customizar headers), em `config/routes.php`:
>
> ```php
> use Hyperf\HttpMessage\Server\Response;
> use Hyperf\HttpMessage\Stream\SwooleFileStream;
> use Hyperf\HttpServer\Router\Router;
> use Hyperf\Support\MimeTypeExtensionGuesser;
>
> Router::get('/public/{path:.+}', function (string $path) {
>     $base = realpath(BASE_PATH . '/storage/public');
>     $file = realpath(BASE_PATH . '/storage/public/' . $path);
>
>     if ($base === false || $file === false
>         || ! str_starts_with($file, $base . DIRECTORY_SEPARATOR)
>         || ! is_file($file)) {
>         return (new Response())->withStatus(404);
>     }
>
>     $mime = (new MimeTypeExtensionGuesser())
>         ->guessMimeType(pathinfo($file, PATHINFO_EXTENSION)) ?? 'application/octet-stream';
>
>     return (new Response())
>         ->withHeader('Content-Type', $mime)
>         ->withBody(new SwooleFileStream($file));
> });
> ```
>
> Em produção, o ideal é servir `storage/public` direto pelo **nginx/caddy**.

### Classe `Vite` (uso direto em views ou serviços)

```php
use Hyperf\Support\make;
use OnixSystemsPHP\HyperfInertia\Vite;

$vite = make(Vite::class);

$html = (string) $vite->withEntryPoints(['storage/inertia/js/app.js']); // tags completas
$url  = $vite->asset('storage/inertia/js/app.js');                      // URL de um asset
$hash = $vite->manifestHash();                                          // md5 do manifest (ou null)
```

### CSP nonce

```php
$vite->useCspNonce();                 // gera um nonce de 40 chars
$vite->useCspNonce('meu-nonce');      // ou usa um seu
```

No layout, chame o `Vite` direto quando precisar do nonce nos tags:

```blade
@php
$vite = \Hyperf\Support\make(\OnixSystemsPHP\HyperfInertia\Vite::class);
@endphp
{!! $vite->useCspNonce()->withEntryPoints(['storage/inertia/js/app.js']) !!}
```

### Atributos customizados nos tags

```php
$vite
    ->useScriptTagAttributes(['data-turbo-track' => 'reload'])
    ->useStyleTagAttributes(fn ($src, $url) => ['media' => 'print'])
    ->usePreloadTagAttributes(fn () => false); // desabilita preload
```

### React refresh (HMR)

Em páginas React, inclua no `<head>` do layout:

```blade
<x-react-refresh/>
```

---

## 11. Server-Side Rendering (SSR)

1. Habilite no `.env`:

```bash
INERTIA_SSR_ENABLED=true
INERTIA_SSR_URL=http://127.0.0.1:13714
```

2. Gere o bundle SSR:

```bash
npm run build-ssr   # vite build && vite build --ssr
```

3. Suba o servidor SSR (dentro do container/servidor):

```bash
php ./bin/hyperf.php inertia:start-ssr        # runtime node (ou --runtime=bun)
php ./bin/hyperf.php inertia:stop-ssr
```

Com SSR ativo, a primeira visita renderiza o HTML no servidor
(`x-inertia-head` injeta o `<head>` e `x-inertia-body` injeta o corpo).
Sem o servidor SSR de pé, o pacote degrada silenciosamente para client-side rendering.

---

## 12. Testes com `assertInertia`

> **Novidade desta versão:** as macros `assertInertia` e `inertiaPage` agora são
> registradas automaticamente no `TestResponse` do Hyperf (via listener) — antes
> dependiam de APIs do Laravel e não funcionavam.

```php
use Hyperf\Testing\TestCase;

class HomeTest extends TestCase
{
    public function testHomeRendersInertiaPage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('title')
                ->where('title', 'Minha página')
                ->missing('metrics'));
    }

    public function testPagePayload(): void
    {
        $page = $this->get('/')->inertiaPage();

        $this->assertSame('Home', $page['component']);
    }
}
```

### Métodos do objeto `AssertableInertia`

| Método | Descrição |
| --- | --- |
| `component('Home')` | Verifica o componente |
| `url('/sobre')` | Verifica a URL |
| `version('abc')` | Verifica a versão |
| `has('user.name')` | Prop existe |
| `missing('metrics')` | Prop **não** existe |
| `where('user.name', 'Ana')` | Valor exato (aceita closure) |
| `whereAll([...])` | Vários `where` de uma vez |
| `etc()` | Ignora props restantes |

Funciona tanto com resposta **HTML** (primeira visita) quanto **JSON** (`X-Inertia`):

```php
$this->get('/', [], [
    'X-Inertia' => 'true',
    'X-Inertia-Version' => $this->assetVersion(),
])->assertJsonPath('component', 'Home');
```

---

## 13. Componentes Blade

Registrados automaticamente pelo pacote:

| Componente | Uso |
| --- | --- |
| `<x-inertia-head :page="$page" :resource="$resource"/>` | Tags do Vite + `<head>` do SSR |
| `<x-inertia-body :page="$page"/>` | Div raiz com `data-page` (ou corpo SSR) |
| `<x-react-refresh/>` | Script do React Refresh (só em HMR) |

> `$resource` são os entrypoints do Vite, ex. `['storage/inertia/js/app.js']`.

---

## 14. Comandos

```bash
php ./bin/hyperf.php inertia:init          # scaffolding do frontend (escolha a stack)
php ./bin/hyperf.php inertia:start-ssr     # inicia o servidor SSR (node/bun)
php ./bin/hyperf.php inertia:stop-ssr      # para o servidor SSR
php ./bin/hyperf.php vendor:publish onix-systems-php/hyperf-inertia   # config + middleware
```

---

## 15. Referência de configuração

`config/autoload/inertia.php` (publicada):

| Chave | Default | Descrição |
| --- | --- | --- |
| `root_view` | `inertia` | Template raiz, em `storage/view/layouts/<nome>.blade.php` |
| `public_path_prefix` | `.../storage/public` | Pasta onde o Vite escreve o build |
| `asset_public_path` | `DOMAIN_API + /public` | Prefixo público das URLs de assets |
| `skip_url_prefix` | `[]` | Prefixos de URL que **pulam** o Inertia (ex. `api`) |
| `no_skip_extra_path` | `[]` | Caminhos que **nunca** pulam, mesmo dentro do prefixo |
| `is_secure` | `true` | Força scheme `https` na request |
| `ssr.enabled` | `false` | Liga SSR |
| `ssr.url` | `http://127.0.0.1:13714` | URL do servidor SSR |
| `ssr.bundle` | `null` | Caminho do bundle SSR (auto-detecta se null) |
| `testing.ensure_pages_exist` | `false` | Falha `component()` se a página não existir em disco |
| `testing.page_paths` | `storage/inertia/js/Pages` | Onde procurar páginas nos testes |
| `testing.page_extensions` | `js,jsx,svelte,ts,tsx,vue` | Extensões aceitas |

---

## 16. Variáveis de ambiente

| Variável | Default | Exemplo |
| --- | --- | --- |
| `INERTIA_SSR_ENABLED` | `false` | `true` |
| `INERTIA_SSR_URL` | `http://127.0.0.1:13714` | `http://ssr:13714` |
| `INERTIA_IS_SECURE` | `true` | `false` (local/HTTP) |
| `INERTIA_SKIP_URL_PREFIX` | vazio | `v1,api` |
| `INERTIA_NO_SKIP_EXTRA_PATH` | vazio | `admin/login` |
| `DOMAIN_API` | vazio | `https://app.exemplo.com` |

---

## 17. Vue, React ou Svelte

O `inertia:init` pergunta a stack e instala as dependências no `package.json`:

| Stack | Pacotes principais |
| --- | --- |
| **vue** | `vue ^3.5`, `@inertiajs/vue3 ^2.0`, `@vitejs/plugin-vue ^5.2`, `vite ^6` |
| **react** | `react ^18.3`, `@inertiajs/react ^2.0`, `@vitejs/plugin-react ^4.4`, `vite ^6` |
| **svelte** | `svelte ^5`, `@inertiajs/svelte ^2.0`, `@sveltejs/vite-plugin-svelte ^5`, `vite ^6` |

Estrutura gerada:

```
storage/inertia/
├── css/app.css
└── js/
    ├── app.js            (ou app.jsx)
    ├── bootstrap.js
    ├── Components/  Layouts/  Pages/
    └── ssr.js            (opcional, se escolher SSR)
storage/view/layouts/inertia.blade.php
vite.config.js
```

> **Svelte:** adicione `"type": "module"` no `package.json`.
> Exemplos completos de páginas/componentes: pasta `example/` do pacote.

---

## 18. Solução de problemas

| Sintoma | Causa provável | Solução |
| --- | --- | --- |
| Chunks (`Home-xxx.js`, `App-xxx.css`) dão 404/`NS_ERROR_CORRUPTED_CONTENT`, mas o `app.js` carrega | `base` do Vite errado (preloads/CSS dos chunks viram `/assets/...`) | `base: '/build/'` no `vite.config.js` + rebuild (seção 10) |
| Assets `/build/...` ou `/public/...` retornam **404** | Roteador Hyperf não serve estáticos por padrão | Use o static handler do Swoole (`enable_static_handler` + `document_root`) ou a rota PHP — seção 10 |
| 500 com `manifest.json` não encontrado | Não rodou `npm run build` (ou Vite 6 escreveu em `.vite/`) | Rode o build; o pacote suporta os dois caminhos |
| Página retorna `409` no lugar do JSON | `X-Inertia-Version` divergente/ausente | Envie a versão atual do asset no header |
| Erros de validação não aparecem | `hyperf/validation` ausente ou middleware fora de ordem | `composer require hyperf/validation`; sessão antes do Inertia |
| Sem flash após redirect | `redirect_with()` precisa da sessão iniciada | Garanta `SessionMiddleware` registrado |
| `assertInertia` não existe | `hyperf/testing` não instalado (dev) | `composer require --dev hyperf/testing` |
| HMR não reflete mudanças | Sem arquivo `hot` | Suba `npm run dev` na pasta do app |
| SSR não renderiza | `INERTIA_SSR_ENABLED` ou servidor SSR fora | Verifique env, bundle e `inertia:start-ssr` |

---

## Licença

MIT — veja [LICENSE](LICENSE). Baseado no adaptador original de
[onix-systems-php/hyperf-inertia](https://github.com/onix-systems-php/hyperf-inertia).
