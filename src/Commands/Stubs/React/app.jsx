import './bootstrap';
import '../css/app.css';

import React from 'react'
import {createRoot} from 'react-dom/client'
import {createInertiaApp} from '@inertiajs/react'

const pages = import.meta.glob('./Pages/**/*.jsx');

function resolvePageComponent(name) {
  const importPage = pages[`./Pages/${name}.jsx`];
  if (!importPage) {
    throw new Error(`Unknown page ${name}`);
  }
  return importPage();
}

const appName = import.meta.env.VITE_APP_NAME || 'Hyperf';

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: resolvePageComponent,
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
  progress: {
    color: '#4B5563',
  },
})
