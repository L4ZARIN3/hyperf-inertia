import './bootstrap';
import '../css/app.css';

import {createApp, h} from 'vue';
import {createInertiaApp} from '@inertiajs/vue3';

const pages = import.meta.glob('./Pages/**/*.vue');

function resolvePageComponent(name) {
  const importPage = pages[`./Pages/${name}.vue`];
  if (!importPage) {
    throw new Error(`Unknown page ${name}`);
  }
  return importPage();
}

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Hyperf';

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: resolvePageComponent,
  setup({el, App, props, plugin}) {
    const app = createApp({
      render: () => h(App, props),
    });

    app.use(plugin);

    app.mount(el);
  },
  progress: {
    color: '#4B5563',
  },
});
