import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { configureEcho } from '@laravel/echo-vue';
import { registraRitornoSinistro } from './ritornoSinistro';

configureEcho({
    broadcaster: 'reverb',
});

registraRitornoSinistro();

createInertiaApp({
    // Nella scheda del browser sempre e solo il nome del prodotto.
    title: () => 'SinistroPro',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
