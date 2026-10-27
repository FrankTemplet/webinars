<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DynamicForm from '@/components/DynamicForm.vue';
import { useLivePreview, toList, type Theme } from '@/composables/useLivePreview';
import { computed, onMounted } from 'vue';

// Declare global window properties for tracking scripts
declare global {
    interface Window {
        fbq?: (action: string, event: string, params?: any, options?: { eventID?: string }) => void;
        _fbq?: any;
        lintrk?: (action: string, params?: any) => void;
        _linkedin_partner_id?: string;
        _linkedin_data_partner_ids?: string[];
    }
}

interface SocialMediaLink {
    type: string;
    url: string;
}

interface Client {
    slug: string;
    name: string;
    logo?: string;
    social_media?: SocialMediaLink[];
    footer?: string | null;
}

interface FormField {
    type: any;
    name: string;
    label: string;
    required?: boolean;
}

interface TrackingScript {
    platform: 'facebook' | 'linkedin';
    pixel_id?: string;
    partner_id?: string;
    conversion_id?: string;
    enabled: boolean;
}

interface Webinar {
    slug: string;
    title: string;
    show_title?: boolean;
    meta_title?: string;
    description?: string;
    meta_description?: string;
    subtitle?: string;
    header_logo?: string;
    hero_image?: string;
    form_schema?: FormField[];
    sessions?: { id: string; label: string }[];
    sessions_label?: string | null;
    tracking_scripts?: TrackingScript[];
    theme: Theme;
}

interface Props {
    client: Client;
    webinar: Webinar;
    submitUrl: string;
    /** Dentro del iframe del panel: los cambios del formulario llegan en vivo. */
    preview?: boolean;
}

const props = defineProps<Props>();

const { pick, vars: themeVars, fontUrl } = useLivePreview(
    () => props.webinar.theme,
    props.preview === true,
);

// Contenido mostrado: lo que mande el formulario y, si viene vacío, lo que
// ya tenía la página.
const view = computed(() => ({
    title: pick('title', props.webinar.title),
    subtitle: pick('subtitle', props.webinar.subtitle),
    description: pick('description', props.webinar.description),
    show_title: pick<unknown>('show_title', props.webinar.show_title) !== false,
    form_schema: toList<FormField>(pick('form_schema', props.webinar.form_schema)),
    sessions_label: pick('sessions_label', props.webinar.sessions_label),
}));

const socialLinks = computed(() => {
    return props.client.social_media || (props.client as any).socialMedia || [];
});

const getSocialIcon = (type: string) => {
    const icons: Record<string, string> = {
        facebook: 'fa-brands fa-facebook-f',
        instagram: 'fa-brands fa-instagram',
        linkedin: 'fa-brands fa-linkedin-in',
        twitter: 'fa-brands fa-x-twitter',
        youtube: 'fa-brands fa-youtube',
        tiktok: 'fa-brands fa-tiktok',
        website: 'fa-solid fa-globe'
    };
    return icons[type.toLowerCase()] || 'fa-solid fa-globe';
};

// Get enabled tracking scripts
const enabledTrackingScripts = computed(() => {
    return (props.webinar.tracking_scripts || []).filter(script => script.enabled);
});

// Get Facebook Pixel configuration
const facebookPixel = computed(() => {
    return enabledTrackingScripts.value.find(script => script.platform === 'facebook');
});

// Get LinkedIn Insight Tag configuration
const linkedinPixel = computed(() => {
    return enabledTrackingScripts.value.find(script => script.platform === 'linkedin');
});

onMounted(() => {
    // Dentro del panel no se cargan pixeles: ensuciarían las métricas.
    if (props.preview) return;

    // Inject Facebook Pixel
    if (facebookPixel.value && facebookPixel.value.pixel_id) {
        if (!window.fbq) {
            (function(f: any, b: Document, e: string, v: string, n?: any, t?: any, s?: any) {
                if (f.fbq) return;
                n = f.fbq = function(...args: any) {
                    // eslint-disable-next-line @typescript-eslint/no-unused-expressions
                    n.callMethod ? n.callMethod(...args) : n.queue.push(args);
                };
                if (!f._fbq) f._fbq = n;
                n.push = n; n.loaded = !0; n.version = '2.0';
                n.queue = [];
                t = b.createElement(e); t.async = !0;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                if (s && s.parentNode) s.parentNode.insertBefore(t, s);
            })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

            (window as any).fbq?.('init', facebookPixel.value.pixel_id);
            // PageView removed to prevent triggering custom events on page load
        }
    }

    // Inject LinkedIn Insight Tag
    if (linkedinPixel.value && linkedinPixel.value.partner_id) {
        if (!window.lintrk) {
            window._linkedin_partner_id = linkedinPixel.value.partner_id;
            window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
            window._linkedin_data_partner_ids.push(window._linkedin_partner_id);

            (function(l: any) {
                if (!l) {
                    window.lintrk = function(a: string, b: any) {
                        (window.lintrk as any).q.push([a, b]);
                    };
                    (window.lintrk as any).q = [];
                }

                const s = document.getElementsByTagName('script')[0];
                const b = document.createElement('script');
                b.type = 'text/javascript'; b.async = true;
                b.src = 'https://snap.licdn.com/li.lms-analytics/insight.min.js';
                if (s && s.parentNode) s.parentNode.insertBefore(b, s);
            })(window.lintrk);
        }
    }
});
</script>

<template>
    <Head :title="webinar.meta_title || view.title">
        <meta name="description" :content="webinar.meta_description || webinar.description" />
        <link v-if="client.logo" rel="icon" :href="`/storage/${client.logo}`">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link v-if="fontUrl" rel="stylesheet" :href="fontUrl">

        <!-- Noscript fallbacks are still good in the head -->
        <noscript v-if="facebookPixel">
            <img alt="pixel" height="1" width="1" style="display:none"
            :src="`https://www.facebook.com/tr?id=${facebookPixel.pixel_id}&ev=PageView&noscript=1`"/>
        </noscript>
        <noscript v-if="linkedinPixel">
            <img height="1" width="1" style="display:none" alt=""
            :src="`https://px.ads.linkedin.com/collect/?pid=${linkedinPixel.partner_id}&fmt=gif`" />
        </noscript>
    </Head>

    <div class="webinar-page" :style="themeVars">
        <main class="header min-h-screen">
            <div class="container-fluid mx-auto px-0">
                <div class="flex flex-wrap h-full">

                    <!-- Left Column: Form & Content -->
                    <div class="form-column w-full md:w-5/12 px-6 md:px-12 py-12 md:py-0 order-2 md:order-1 h-full relative z-10">
                        <div class="wrapper px-2 md:px-8 max-w-xl mx-auto md:mr-0 md:ml-auto md:min-h-screen flex flex-col justify-center">

                            <header class="mb-4 mt-3">
                                <div class="logo pb-4 mb-3 xl:mb-6">
                                    <img
                                        v-if="webinar.header_logo"
                                        :src="`/storage/${webinar.header_logo}`"
                                        alt="Logos"
                                        class="h-[60px] w-auto max-w-full"
                                    >
                                    <img
                                        v-else-if="client.logo"
                                        :src="`/storage/${client.logo}`"
                                        alt="Client Logo"
                                        class="h-[60px] w-auto max-w-full"
                                    >
                                </div>
                                <h1 v-if="view.show_title" class="webinar-title font-bold text-[34px] md:text-[38px] leading-[1.1] mb-3">
                                    {{ view.title }}
                                </h1>
                                <p v-if="view.subtitle" class="webinar-subtitle text-[20px] leading-[26px] mb-0 tracking-normal font-bold">
                                    {{ view.subtitle }}
                                </p>
                                <div v-if="view.description" class="webinar-description text-[16px] mb-4" v-html="view.description"></div>
                            </header>

                            <!-- Dynamic Form Component -->
                            <div class="w-full">
                                <DynamicForm
                                    v-if="view.form_schema && view.form_schema.length"
                                    :schema="view.form_schema"
                                    :sessions="webinar.sessions"
                                    :sessions-label="view.sessions_label"
                                    :submit-url="submitUrl"
                                    :tracking-scripts="enabledTrackingScripts"
                                    :preview="preview"
                                />
                                <div v-else class="text-center text-gray-500 py-4">
                                    Formulario no disponible.
                                </div>
                            </div>

                            <footer class="mt-8 md:mt-12 mb-8">
                                <div class="block mt-3">
                                    <p class="text-[12px] mb-2">Síguenos:</p>
                                    <div class="flex mb-4" v-if="socialLinks.length">
                                        <a v-for="link in socialLinks"
                                           :key="link.type"
                                           :href="link.url"
                                           target="_blank"
                                           class="social-link mr-4 transition-all text-xl"
                                           :title="link.type">
                                            <i :class="getSocialIcon(link.type)"></i>
                                        </a>
                                    </div>
                                    <p class="text-[12px] mb-1 mt-3 xl:mt-5">
                                        {{ client.footer || `Copyright © | ${new Date().getFullYear()} ${client.name} | All rights reserved.` }}
                                    </p>
                                </div>
                            </footer>

                        </div>
                    </div>

                    <!-- Right Column: Hero Image -->
                    <div class="w-full md:w-7/12 order-1 md:order-2 p-0 relative min-h-[50vh] md:min-h-screen flex justify-end">
                        <div class="w-full h-full absolute inset-0 bg-cover bg-center bg-no-repeat"
                             :style="`background-image: url('/storage/${webinar.hero_image}');`"
                             v-if="webinar.hero_image">
                        </div>
                        <!-- Fallback gray background if no image -->
                        <div v-else class="w-full h-full absolute inset-0 bg-gray-200"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style>
/* Los tokens (--font, --accent, --heading, --subtitle, --text, --bg y los
   radios) los inyecta el servidor en el div raíz desde la configuración del
   webinar. Lo que antes era un bloque fijo para una sola landing ahora sale
   de estas variables. */
.webinar-page {
    font-family: var(--font);
    color: var(--text);
}

.webinar-page,
.webinar-page input,
.webinar-page textarea,
.webinar-page select,
.webinar-page button,
.webinar-page label,
.webinar-page p {
    font-family: var(--font);
}

.form-column {
    background-color: var(--bg);
}

.webinar-title {
    color: var(--heading);
}

.webinar-subtitle {
    color: var(--subtitle);
}

.webinar-description,
.webinar-description p {
    color: var(--text);
}

.webinar-description a {
    color: var(--accent);
}

.social-link {
    color: var(--text);
}

.social-link:hover {
    color: var(--accent);
}

.font-infra {
    font-family: 'Infra', var(--font);
}
</style>
