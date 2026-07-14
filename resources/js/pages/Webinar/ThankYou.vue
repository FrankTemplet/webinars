<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Client {
    slug: string;
    name: string;
    logo?: string;
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
}

interface Props {
    client: Client;
    webinar: Webinar;
    webinarUrl: string;
}

const props = defineProps<Props>();

const title = computed(() => props.webinar.thank_you_title || '¡Gracias por registrarte!');
const message = computed(() => props.webinar.thank_you_message || '<p>Recibirás un correo con los detalles del webinar.</p>');
const ctaText = computed(() => props.webinar.thank_you_cta_text || 'Volver');
const ctaUrl = computed(() => props.webinar.thank_you_cta_url || props.webinarUrl);
</script>

<template>
    <Head :title="webinar.meta_title || webinar.title">
        <meta name="description" :content="webinar.meta_description" />
        <link rel="icon" type="image/x-icon" :href="`/storage/${client.logo}`" v-if="client.logo">
    </Head>

    <div class="font-roboto text-[#656668]">
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
            <div class="relative z-10 w-full max-w-xl bg-white rounded-[5px] shadow-xl px-8 md:px-12 py-10 text-center">
                <div class="flex justify-center mb-6">
                    <img v-if="webinar.header_logo" :src="`/storage/${webinar.header_logo}`" alt="Logo" class="h-[60px] w-auto">
                    <img v-else-if="client.logo" :src="`/storage/${client.logo}`" alt="Client Logo" class="h-[60px] w-auto">
                </div>

                <h1 class="text-[#041B36] font-bold text-[30px] md:text-[34px] leading-[1.1] mb-4 font-roboto">
                    {{ title }}
                </h1>

                <div class="text-[#656668] text-[16px] mb-8 font-roboto" v-html="message"></div>

                <a :href="ctaUrl"
                   class="inline-block w-auto text-center align-middle cursor-pointer select-none border border-transparent px-[1.75rem] py-[0.625rem] text-[1rem] leading-[1.5] rounded-none uppercase font-bold text-white bg-[#00B0D3] hover:bg-white hover:text-[#00B0D3] hover:border-[#00B0D3] transition-colors duration-150 ease-in-out">
                    {{ ctaText }}
                </a>

                <p class="text-[12px] text-[#656668] mt-8 mb-0">
                    Copyright © | {{ new Date().getFullYear() }} {{ client.name }} | All rights reserved.
                </p>
            </div>
        </main>
    </div>
</template>

<style>
.font-roboto {
    font-family: 'Roboto', sans-serif;
}
</style>
