import ReactDOMServer from 'react-dom/server';
import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';

const pages = import.meta.glob('./Pages/**/*.jsx');

function resolvePageComponent(name) {
  const importPage = pages[`./Pages/${name}.jsx`];
  if (!importPage) {
    throw new Error(`Unknown page ${name}`);
  }
  return importPage();
}

const appName = import.meta.env.VITE_APP_NAME || 'Hyperf';

createServer((page) =>
  createInertiaApp({
    page,
    render: ReactDOMServer.renderToString,
    title: (title) => `${title} - ${appName}`,
    resolve: resolvePageComponent,
    setup: ({ App, props }) => <App {...props} />,
  })
);
