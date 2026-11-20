# Início Rápido — Inertia.js no Hyperf

> Da instalação à primeira página em ~15 minutos.
> Documentação completa: [DOCUMENTACAO.md](DOCUMENTACAO.md)

---

## 0 · Pré-requisitos

- PHP ≥ 8.2 com extensão **Swoole** (ou Swow)
- [Hyperf](https://hyperf.io) 3.2
- Node ≥ 18 + npm

---

## 1 · Instalar o pacote

```bash
composer require onix-systems-php/hyperf-inertia
composer require hyperf/validation        # erros de formulário compartilhados

php ./bin/hyperf.php vendor:publish onix-systems-php/hyperf-inertia   # config + middleware
php ./bin/hyperf.php vendor:publish hyperf/session                     # config de sessão
php ./bin/hyperf.php vendor:publish hyperf/view-engine                 # config da view (Blade)
```

---

## 2 · Registrar os middlewares

`config/autoload/middlewares.php`:

```php
return [
    'http' => [
        Hyperf\Session\Middleware\SessionMiddleware::class,
        App\Common\Middleware\HandleInertiaMiddleware::class,
    ],
];
```

> Sessão **antes** do Inertia. O middleware Inertia já captura `ValidationException` —
> não registre o middleware de validação.

---

## 3 · Inicializar o frontend

```bash
php ./bin/hyperf.php inertia:init     # escolha: vue | react | svelte
npm install
```

Estrutura gerada: `storage/inertia/js/{Pages,Layouts,Components}/` +
`storage/view/layouts/inertia.blade.php` + `vite.config.js`.

---

## 4 · ⚠️ 3 ajustes obrigatórios (assets)

**a) `vite.config.js`** — defina o `base`:

```js
export default defineConfig({
  base: '/build/',                     // sem isso, chunks dinâmicos dão 404
  plugins: [react()],                  // ou vue() / svelte()
  build: {
    manifest: true,
    outDir: 'storage/public/build',
    rollupOptions: { input: 'storage/inertia/js/app.jsx' }, // .js para vue/svelte
  },
});
```

**b) `config/autoload/inertia.php`** — remova o sufixo `/public` para as URLs
baterem com o `document_root`:

```php
'asset_public_path' => env('DOMAIN_API', ''),   // era: env('DOMAIN_API') . '/public'
```

**c) `config/autoload/server.php`** — deixe o **Swoole** servir os estáticos
(no servidor `http`):

```php
'settings' => [
    'enable_static_handler' => true,
    'document_root' => BASE_PATH . '/storage/public',
],
```

> Regra de ouro: `base` (Vite) + `asset_public_path` (Hyperf) + `document_root`
> (Swoole) devem apontar para o mesmo lugar público: URLs `/build/assets/...`
> → arquivos em `storage/public/build/`.

---

## 5 · Primeira página

**Controller:**

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
        return inertia('Home', ['title' => 'Olá, Inertia!'])->toResponse($this->request);
    }
}
```

**Página (React):** `storage/inertia/js/Pages/Home.jsx`

```jsx
export default function Home({ title }) {
  return <h1>{title}</h1>;
}
```

> Nome do componente vira caminho: `'Home'` → `Pages/Home.jsx`, `'Admin/Login'` → `Pages/Admin/Login.jsx`.

---

## 6 · Rodar

```bash
npm run build                  # gera storage/public/build (manifest)
php bin/hyperf.php start       # sobe em http://127.0.0.1:9501 — Ctrl+C para
```

- Após um **rebuild**, o pacote detecta o manifest novo sozinho (não precisa reiniciar o servidor);
  no navegador, use **Ctrl+Shift+R** uma vez.
- Dev com HMR: `npm run dev` em outro terminal.

---

## 7 · Respostas, flash e validação (receitas rápidas)

```php
// render
return inertia('Dashboard', ['user' => $user])->toResponse($request);

// lazy prop (só em partial reloads)
return inertia('Dashboard', ['metrics' => inertia()->lazy(fn () => $this->metrics())])
    ->toResponse($request);

// flash + redirect
return redirect_with('/contato', ['alert' => ['type' => 'success', 'message' => 'Enviado!']]);

// redirecionamento externo
return inertia_location('https://inertiajs.com');

// validação — erros viram a prop compartilhada `errors` automaticamente
$validator = $this->validationFactory->make($request->all(), [
    'email' => 'required|email',
    'password' => 'required|min:6',
]);
$validator->validate();
```

No frontend (React), erros voltam direto no `useForm`:

```jsx
const { data, setData, post, processing, errors } = useForm({ email: '', password: '' });
// <form onSubmit={(e) => { e.preventDefault(); post('/login'); }}>
// {errors.email && <p>{errors.email}</p>}
```

---

## 8 · Testes (opcional)

```bash
composer require --dev hyperf/testing
```

```php
use Hyperf\Testing\TestCase;

class HomeTest extends TestCase
{
    public function testHome(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('title')
                ->where('title', 'Olá, Inertia!'));
    }
}
```

---

## 9 · SSR (opcional)

```bash
# .env
INERTIA_SSR_ENABLED=true

npm run build-ssr
php ./bin/hyperf.php inertia:start-ssr     # parar: inertia:stop-ssr
```

---

## 10 · Problemas comuns

| Sintoma | Causa | Solução |
| --- | --- | --- |
| Assets `/build/...` dão 404 | Static handler não configurado | Passo 4c (Swoole) ou rota PHP (ver DOCUMENTACAO.md §10) |
| Chunks (`Home-*.js`) dão 404, `app.js` carrega | `base` do Vite errado | Passo 4a + rebuild |
| `The ext-swoole is required` | Swoole não carregado no PHP CLI | Instale a extensão / verifique `php -m` |
| Erros de validação não aparecem | `hyperf/validation` ausente ou ordem dos middlewares | Passo 1 e 2 |
| `assertInertia` não existe | `hyperf/testing` ausente | Passo 8 |

---

## Próximos passos

- Shared props, partial reloads, CSP nonce, componentes Blade, referência de config:
  [DOCUMENTACAO.md](DOCUMENTACAO.md)
- Exemplo completo (login, validação, lazy props, testes): projeto demo `pentest-site`.
