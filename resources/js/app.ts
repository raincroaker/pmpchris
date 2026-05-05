import { createInertiaApp } from '@inertiajs/vue3';
import Aura from '@primeuix/themes/aura';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PrimeVue from 'primevue/config';
import { ConfigProvider } from 'reka-ui';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import 'primeicons/primeicons.css';
import 'vue-sonner/style.css';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '../css/app.css';
import AppToaster from '@/components/AppToaster.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () =>
                h(
                    ConfigProvider,
                    { scrollBody: { padding: 0, margin: 0 } },
                    () =>
                        h('div', { class: 'contents' }, [
                            h(App, props),
                            h(AppToaster),
                        ]),
                ),
        })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        prefix: 'p',
                        darkModeSelector: 'html.dark',
                    },
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
