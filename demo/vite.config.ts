import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import mudletWeb from '@mudlet/mudlet-web/vite';

// base './' so the built dist/ can be dropped at any path on mudlet.org and
// still resolve its own chunks — the page embeds it as a same-origin iframe.
export default defineConfig({
    base: './',
    plugins: [mudletWeb(), react()],
});
