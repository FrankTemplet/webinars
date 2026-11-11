<script setup lang="ts">
import DynamicForm from '@/components/DynamicForm.vue';
import {
    toList,
    useLivePreview,
    type Theme,
} from '@/composables/useLivePreview';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

// Declare global window properties for tracking scripts
declare global {
    interface Window {
        fbq?: (
            action: string,
            event: string,
            params?: any,
            options?: { eventID?: string },
        ) => void;
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
    favicon?: string;
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
    mobile_hero_image?: string;
    submit_label?: string;
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

const {
    pick,
    imageUrl,
    vars: themeVars,
    fontUrl,
} = useLivePreview(() => props.webinar.theme, props.preview === true);

// Contenido mostrado: lo que mande el formulario y, si viene vacío, lo que
// ya tenía la página.
const view = computed(() => ({
    favicon:
        imageUrl('favicon', props.client.favicon) ||
        imageUrl('logo', props.client.logo),
    hero: imageUrl('hero_image', props.webinar.hero_image),
    mobile: imageUrl('mobile_hero_image', props.webinar.mobile_hero_image),
    submit_label: pick('submit_label', props.webinar.submit_label),
    title: pick('title', props.webinar.title),
    subtitle: pick('subtitle', props.webinar.subtitle),
    description: pick('description', props.webinar.description),
    show_title: pick<unknown>('show_title', props.webinar.show_title) !== false,
    form_schema: toList<FormField>(
        pick('form_schema', props.webinar.form_schema),
    ),
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
        website: 'fa-solid fa-globe',
    };
    return icons[type.toLowerCase()] || 'fa-solid fa-globe';
};

// Get enabled tracking scripts
const enabledTrackingScripts = computed(() => {
    return (props.webinar.tracking_scripts || []).filter(
        (script) => script.enabled,
    );
});

// Get Facebook Pixel configuration
const facebookPixel = computed(() => {
    return enabledTrackingScripts.value.find(
        (script) => script.platform === 'facebook',
    );
});

// Get LinkedIn Insight Tag configuration
const linkedinPixel = computed(() => {
    return enabledTrackingScripts.value.find(
        (script) => script.platform === 'linkedin',
    );
});

onMounted(() => {
    // Dentro del panel no se cargan pixeles: ensuciarían las métricas.
    if (props.preview) return;

    // Inject Facebook Pixel
    if (facebookPixel.value && facebookPixel.value.pixel_id) {
        if (!window.fbq) {
            (function (
                f: any,
                b: Document,
                e: string,
                v: string,
                n?: any,
                t?: any,
                s?: any,
            ) {
                if (f.fbq) return;
                n = f.fbq = function (...args: any) {
                    // eslint-disable-next-line @typescript-eslint/no-unused-expressions
                    n.callMethod ? n.callMethod(...args) : n.queue.push(args);
                };
                if (!f._fbq) f._fbq = n;
                n.push = n;
                n.loaded = !0;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = !0;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                if (s && s.parentNode) s.parentNode.insertBefore(t, s);
            })(
                window,
                document,
                'script',
                'https://connect.facebook.net/en_US/fbevents.js',
            );

            (window as any).fbq?.('init', facebookPixel.value.pixel_id);
            // PageView removed to prevent triggering custom events on page load
        }
    }

    // Inject LinkedIn Insight Tag
    if (linkedinPixel.value && linkedinPixel.value.partner_id) {
        if (!window.lintrk) {
            window._linkedin_partner_id = linkedinPixel.value.partner_id;
            window._linkedin_data_partner_ids =
                window._linkedin_data_partner_ids || [];
            window._linkedin_data_partner_ids.push(window._linkedin_partner_id);

            (function (l: any) {
                if (!l) {
                    window.lintrk = function (a: string, b: any) {
                        (window.lintrk as any).q.push([a, b]);
                    };
                    (window.lintrk as any).q = [];
                }

                const s = document.getElementsByTagName('script')[0];
                const b = document.createElement('script');
                b.type = 'text/javascript';
                b.async = true;
                b.src =
                    'https://snap.licdn.com/li.lms-analytics/insight.min.js';
                if (s && s.parentNode) s.parentNode.insertBefore(b, s);
            })(window.lintrk);
        }
    }
});
</script>

<template>
    <Head :title="webinar.meta_title || view.title">
        <meta
            name="description"
            :content="webinar.meta_description || webinar.description"
        />
        <link v-if="view.favicon" rel="icon" :href="view.favicon" />
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link v-if="fontUrl" rel="stylesheet" :href="fontUrl" />

        <!-- Noscript fallbacks are still good in the head -->
        <noscript v-if="facebookPixel">
            <img
                alt="pixel"
                height="1"
                width="1"
                style="display: none"
                :src="`https://www.facebook.com/tr?id=${facebookPixel.pixel_id}&ev=PageView&noscript=1`"
            />
        </noscript>
        <noscript v-if="linkedinPixel">
            <img
                height="1"
                width="1"
                style="display: none"
                alt=""
                :src="`https://px.ads.linkedin.com/collect/?pid=${linkedinPixel.partner_id}&fmt=gif`"
            />
        </noscript>
    </Head>

    <div class="webinar-page" :style="themeVars">
        <main class="header min-h-screen">
            <div class="container-fluid mx-auto px-0">
                <div class="webinar-layout">
                    <!-- Left Column: Form & Content -->
                    <div class="form-column relative z-10">
                        <div
                            class="form-wrapper mx-auto flex flex-col justify-center"
                        >
                            <header class="mt-3 mb-4">
                                <div class="logo mb-3 pb-4 xl:mb-6">
                                    <img
                                        v-if="webinar.header_logo"
                                        :src="`/storage/${webinar.header_logo}`"
                                        alt="Logos"
                                        class="h-[60px] w-auto max-w-full"
                                    />
                                    <img
                                        v-else-if="client.logo"
                                        :src="`/storage/${client.logo}`"
                                        alt="Client Logo"
                                        class="h-[60px] w-auto max-w-full"
                                    />
                                </div>
                                <h1
                                    v-if="view.show_title"
                                    class="webinar-title mb-3 text-[34px] leading-[1.1] font-bold md:text-[38px]"
                                >
                                    {{ view.title }}
                                </h1>
                                <p
                                    v-if="view.subtitle"
                                    class="webinar-subtitle mb-0 text-[20px] leading-[26px] font-bold tracking-normal"
                                >
                                    {{ view.subtitle }}
                                </p>
                                <div
                                    v-if="view.description"
                                    class="webinar-description mb-4 text-[16px]"
                                    v-html="view.description"
                                ></div>
                            </header>

                            <!-- Dynamic Form Component -->
                            <div class="w-full">
                                <DynamicForm
                                    :key="JSON.stringify(view.form_schema)"
                                    v-if="
                                        view.form_schema &&
                                        view.form_schema.length
                                    "
                                    :schema="view.form_schema"
                                    :sessions="webinar.sessions"
                                    :sessions-label="view.sessions_label"
                                    :submit-url="submitUrl"
                                    :tracking-scripts="enabledTrackingScripts"
                                    :preview="preview"
                                    :submit-label="view.submit_label"
                                />
                                <div
                                    v-else
                                    class="py-4 text-center text-gray-500"
                                >
                                    Formulario no disponible.
                                </div>
                            </div>

                            <footer class="mt-8 mb-8 md:mt-12">
                                <div class="mt-3 block">
                                    <p class="mb-2 text-[12px]">Síguenos:</p>
                                    <div
                                        class="mb-4 flex"
                                        v-if="socialLinks.length"
                                    >
                                        <a
                                            v-for="link in socialLinks"
                                            :key="link.type"
                                            :href="link.url"
                                            target="_blank"
                                            class="social-link mr-4 text-xl transition-all"
                                            :title="link.type"
                                        >
                                            <i
                                                :class="
                                                    getSocialIcon(link.type)
                                                "
                                            ></i>
                                        </a>
                                    </div>
                                    <p class="mt-3 mb-1 text-[12px] xl:mt-5">
                                        {{
                                            client.footer ||
                                            `Copyright © | ${new Date().getFullYear()} ${client.name} | All rights reserved.`
                                        }}
                                    </p>
                                </div>
                            </footer>
                        </div>
                    </div>

                    <!-- Right Column: Hero Image -->
                    <div
                        class="hero-column"
                        :style="{
                            '--hero-image': view.hero
                                ? `url(${view.hero})`
                                : undefined,
                        }"
                    >
                        <picture
                            v-if="view.hero || view.mobile"
                            class="hero-picture block w-full"
                        >
                            <source
                                v-if="view.mobile"
                                media="(max-width: 767px)"
                                :srcset="view.mobile"
                            />
                            <img
                                :src="view.hero || view.mobile"
                                :alt="view.title"
                                class="webinar-hero block h-auto w-full"
                            />
                        </picture>
                        <div
                            v-else
                            class="hero-placeholder min-h-[50svh] w-full bg-gray-200"
                        ></div>
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
    background-color: var(--bg);
    min-height: 100svh;
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

.webinar-layout {
    display: grid;
    grid-template-areas: 'hero' 'form';
    grid-template-columns: minmax(0, 1fr);
    align-items: stretch;
}
.form-column {
    grid-area: form;
    min-width: 0;
    padding: 2rem 1.5rem;
    background-color: var(--bg);
}
.form-wrapper {
    width: 100%;
    max-width: 40rem;
}
.hero-column {
    grid-area: hero;
    position: relative;
    min-width: 0;
    /* clip y no hidden: hidden rompe el sticky de la imagen. */
    overflow: clip;
    background-color: var(--bg);
}
@media (min-width: 1024px) {
    .webinar-layout {
        grid-template-areas: 'form hero';
        grid-template-columns: minmax(380px, 5fr) minmax(0, 7fr);
    }
    .form-column {
        padding: 1.5rem clamp(1.5rem, 3vw, 3rem);
    }
    .form-wrapper {
        max-width: 35rem;
        min-height: calc(100svh - 3rem);
    }
    /* La imagen se muestra completa; el espacio que sobra cuando el
       formulario es más alto se rellena con la misma imagen difuminada. */
    .hero-column::before {
        content: '';
        position: absolute;
        inset: -3rem;
        background: var(--hero-image, none) center / cover no-repeat;
        filter: blur(40px);
    }
    .hero-picture {
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .webinar-hero {
        max-height: 100svh;
        object-fit: contain;
        object-position: center top;
    }
    .hero-placeholder {
        min-height: 100svh;
    }
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
