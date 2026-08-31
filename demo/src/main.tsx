import { createRoot } from 'react-dom/client';
import { MudletWebApp, type BrandConfig } from '@mudlet/mudlet-web';
import '@mudlet/mudlet-web/styles.css';
import './embed.css';
import { AutoLanding } from './AutoLanding';
import demoPackageUrl from './assets/mudlet-demo.mpackage?url';
import runLuaCodeUrl from './assets/run-lua-code.mpackage?url';

const brand: BrandConfig = {
    appName: 'Mudlet',
    // Branded mode (one profile, no picker, nothing persisted) is switched on
    // by pinning a MUD — so the embed pins one it never dials. AutoLanding
    // always opens offline; the world is Lua inside the demo package.
    mud: { mode: 'websocket', url: 'wss://demo.invalid/', name: 'Mudlet demo', autoConnect: false },
    // One profile per tab, not one shared: a profile can only be open in a
    // single tab, and a second homepage tab must not land on the resulting
    // "open in another tab" screen. AutoLanding supplies the account name that
    // picks the profile.
    profileMode: 'perLogin',
    // Exact, not additive: the stock defaults bring a mapper too, which is
    // only noise in a four-room demo — but run-lua-code earns its place, so
    // it is listed explicitly.
    packages: [
        {
            name: 'mudlet-demo',
            filename: 'mudlet-demo.mpackage',
            url: demoPackageUrl,
            version: '0.18.0',   // bump with config.lua to push a new world to returning visitors
            removable: false,
        },
        {
            name: 'run-lua-code',
            filename: 'run-lua-code.mpackage',
            url: runLuaCodeUrl,
            version: '6',
            removable: false,
        },
    ],
    // Matches the hero's terminal palette, so the swap from the static poster
    // to the live client isn't a colour change.
    themes: [
        {
            id: 'mudlet-site',
            label: 'Mudlet',
            colorScheme: 'dark',
            variables: {
                '--bg': '#1e1813',
                '--bg-surface': '#2b231b',
                '--bg-input': '#241d17',
                '--bg-glass': 'rgba(30, 24, 19, 0.72)',
                '--border': '#3a2f24',
                '--text': '#e8dcc6',
                '--text-dim': '#9c8f7c',
                '--accent': '#f56c27',
                '--accent-dim': '#b83d00',
                '--accent-glow': 'rgba(245, 108, 39, 0.18)',
                '--accent-focus': 'rgba(245, 108, 39, 0.28)',
                '--console-bg': '#1e1813',
                '--console-text': '#e8dcc6',
                '--btn-primary-text': '#1e1813',
            },
        },
    ],
    availableThemes: ['mudlet-site'],
    defaultTheme: 'mudlet-site',
    Landing: AutoLanding,
};

createRoot(document.getElementById('root')!).render(<MudletWebApp brand={brand} />);

// The page keeps its static terminal on screen until the client has actually
// printed something — booting React, the Lua VM and PCRE2 takes a beat, and a
// cross-fade into a live session reads better than one into an empty console.
// Polling beats an event here: nothing in the library announces "first line
// rendered", and the DOM is the thing the visitor is waiting for.
(function announceReady() {
    const started = Date.now();
    const tick = window.setInterval(() => {
        const out = document.querySelector('.output-container');
        // The demo package's own boot line — the page is showing an identical
        // copy, and lifts it the moment this lands.
        const ready = out?.textContent?.includes('connecting') ?? false;
        // A profile is single-owner across tabs, so a second tab on the same
        // origin gets ProfileBusyScreen — "open in another tab" — and would
        // otherwise sit there until the page's own timeout gave up. Report the
        // failure at once instead, so the hero falls straight back to its
        // scripted terminal and the visitor never sees the message.
        const busy = !ready && document.querySelector('.profile-busy') !== null;
        if (!ready && !busy && Date.now() - started < 10000) return;
        window.clearInterval(tick);
        window.parent?.postMessage({ type: 'mudlet-demo:ready', ok: ready }, '*');
    }, 120);
})();

