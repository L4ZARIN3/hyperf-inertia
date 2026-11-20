import createServer from "@inertiajs/svelte/server";
import { createInertiaApp } from "@inertiajs/svelte";
import { render } from "svelte/server";

const pages = import.meta.glob("./Pages/**/*.svelte", { eager: true });

createServer((page) =>
  createInertiaApp({
    page,
    resolve: (name) => {
      const pageModule = pages[`./Pages/${name}.svelte`];
      if (!pageModule) {
        throw new Error(`Unknown page ${name}`);
      }
      return pageModule;
    },
    setup: ({ App, props }) => render(App, { props }),
  })
);
