import {createInertiaApp} from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import {renderToString} from '@vue/server-renderer'
import {createSSRApp, h} from 'vue'

const pages = import.meta.glob('./Pages/**/*.vue');

function resolvePageComponent(name) {
  const importPage = pages[`./Pages/${name}.vue`];
  if (!importPage) {
    throw new Error(`Unknown page ${name}`);
  }
  return importPage();
}

const appName = import.meta.env.VITE_APP_NAME || 'Hyperf';

createServer(page =>
  createInertiaApp({
    page,
    title: (title) => `${title} - ${appName}`,
    resolve: resolvePageComponent,
    // renderToString is async since Vue 3.3, but the adapter inlines the
    // result of render() as innerHTML without awaiting it. We pre-render the
    // app inside setup() and let render() pass the rendered HTML string
    // straight through.
    render: (input) => typeof input === 'string' ? input : renderToString(input),
    setup: async ({ App, props, plugin }) => {
      const app = createSSRApp({render: () => h(App, props)}).use(plugin);
      return await renderToString(app);
    },
  }),
)
