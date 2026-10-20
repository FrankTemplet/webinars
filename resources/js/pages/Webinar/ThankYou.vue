<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

interface Client {
    slug: string;
    name: string;
    logo?: string;
    footer?: string | null;
}

interface TrackingScript {
    platform: 'facebook' | 'linkedin';
    pixel_id?: string;
    partner_id?: string;
    enabled: boolean;
}

interface Theme {
    vars: Record<string, string>;
    font_url?: string | null;
    custom_css?: string | null;
}

interface Webinar {
    slug: string;
    title: string;
    meta_title?: string;
    meta_description?: string;
    header_logo?: string;
    thank_you_title?: string;
    thank_you_message?: string;
    thank_you_image?: string;
    thank_you_cta_text?: string;
    thank_you_cta_url?: string;
    tracking_scripts?: TrackingScript[];
    theme: Theme;
}

interface Props {
    client: Client;
    webinar: Webinar;
    webinarUrl: string;
}

const props = defineProps<Props>();

const themeVars = computed(() => props.webinar.theme.vars);

const title = computed(() => props.webinar.thank_you_title || '¡Gracias por registrarte!');
const message = computed(() => props.webinar.thank_you_message || '<p>Recibirás un correo con los detalles del webinar.</p>');
const ctaText = computed(() => props.webinar.thank_you_cta_text || 'Volver');
const ctaUrl = computed(() => props.webinar.thank_you_cta_url || props.webinarUrl);

const facebookPixel = computed(() => {
    return (props.webinar.tracking_scripts || []).find(s => s.platform === 'facebook' && s.enabled);
});

onMounted(() => {
    if (!facebookPixel.value?.pixel_id) return;

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
    }

    (window as any).fbq?.('track', 'ViewContent');
    console.log('Facebook Pixel: ViewContent event triggered on thank you page');
});
</script>

<template>
    <Head :title="webinar.meta_title || webinar.title">
        <meta name="description" :content="webinar.meta_description" />
        <link rel="icon" :href="`/storage/${client.logo}`" v-if="client.logo">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link v-if="webinar.theme.font_url" rel="stylesheet" :href="webinar.theme.font_url">
    </Head>

    <div class="thankyou-page" :style="themeVars">
        <main class="min-h-screen relative flex items-center justify-center px-6 py-12">
            <!-- Full-screen background image -->
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                 :style="`background-image: url('/storage/${webinar.thank_you_image}');`"
                 v-if="webinar.thank_you_image">
            </div>
            <div v-else class="absolute inset-0 bg-gray-200"></div>
            <!-- Dark overlay for card readability -->
            <div class="absolute inset-0 bg-black/30"></div>

            <!-- Thank you card -->
            <div class="thankyou-card relative z-10 w-full max-w-xl shadow-xl px-8 md:px-12 py-10 text-center">
                <div class="flex justify-center mb-6">
                    <img v-if="webinar.header_logo" :src="`/storage/${webinar.header_logo}`" alt="Logo" class="h-[60px] w-auto">
                    <img v-else-if="client.logo" :src="`/storage/${client.logo}`" alt="Client Logo" class="h-[60px] w-auto">
                </div>

                <h1 class="thankyou-title font-bold text-[30px] md:text-[34px] leading-[1.1] mb-4">
                    {{ title }}
                </h1>

                <div class="text-[16px] mb-8" v-html="message"></div>

                <a :href="ctaUrl"
                   class="btn-cta inline-block w-auto text-center align-middle cursor-pointer select-none border px-[1.75rem] py-[0.625rem] text-[1rem] leading-[1.5] transition-colors duration-150 ease-in-out">
                    {{ ctaText }}
                </a>

                <p class="text-[12px] mt-8 mb-0">
                    {{ client.footer || `Copyright © | ${new Date().getFullYear()} ${client.name} | All rights reserved.` }}
                </p>
            </div>
        </main>
    </div>
</template>

<style>
/* Mismos tokens que la landing del webinar. */
.thankyou-page {
    font-family: var(--font);
    color: var(--text);
}

.thankyou-card {
    background-color: var(--bg);
    border-radius: var(--radius-field);
}

.thankyou-title {
    color: var(--heading);
}

.btn-cta {
    color: var(--accent-text);
    background-color: var(--accent);
    border-color: var(--accent);
    border-radius: var(--radius-button);
    text-transform: var(--button-transform, uppercase);
}

.btn-cta:hover,
.btn-cta:focus-visible {
    color: var(--accent);
    background-color: var(--bg);
    border-color: var(--accent);
}
</style>
