import './bootstrap';
import '../css/app.css';

import {createInertiaApp} from '@inertiajs/svelte';

const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true });

function resolvePageComponent(name) {
  const page = pages[`./Pages/${name}.svelte`];
  if (!page) {
    throw new Error(`Unknown page ${name}`);
  }
  return page;
}

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Hyperf';

createInertiaApp({
  resolve: resolvePageComponent,
  setup({ el, App, props }) {
    new App({ target: el, props, hydrate: true })
  },
  progress: {
    color: '#4B5563',
  },
});
