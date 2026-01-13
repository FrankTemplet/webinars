<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DynamicForm from '@/components/DynamicForm.vue';
import { route } from 'ziggy-js';
import { computed } from 'vue';

interface SocialMediaLink {
    type: string;
    url: string;
}

interface Client {
    slug: string;
    name: string;
    logo?: string;
    social_media?: SocialMediaLink[];
}

interface FormField {
    // Define según la estructura de tu schema
    type: any;
    name: string;
    label: string;
    required?: boolean;
    // Añade más propiedades según necesites
}

interface Webinar {
    slug: string;
    title: string;
    meta_title?: string;
    description?: string;
    meta_description?: string;
    subtitle?: string;
    header_logo?: string;
    hero_image?: string;
    form_schema?: FormField[];
}

interface Props {
    client: Client;
    webinar: Webinar;
}

const props = defineProps<Props>();

// Construct the submit URL
// Assuming the client slug is part of the request context handled by Laravel routes
const submitUrl = route('webinar.store.local', {
    client: props.client.slug,
    slug: props.webinar.slug
});

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
</script>

<template>
    <Head :title="webinar.meta_title || webinar.title">
        <meta name="description" :content="webinar.meta_description || webinar.description" />
        <link rel="icon" type="image/x-icon" :href="`/storage/${client.logo}`" v-if="client.logo">
    </Head>

    <!-- Global Font Fix -->
    <div class="font-roboto text-[#656668]">
        <main class="header min-h-screen">
            <div class="container-fluid mx-auto px-0">
                <div class="flex flex-wrap h-full">

                    <!-- Left Column: Form & Content -->
                    <div class="w-full md:w-5/12 px-6 md:px-12 py-12 md:py-0 order-2 md:order-1 h-full relative z-10 bg-white">
                        <div class="wrapper px-2 md:px-8 max-w-xl mx-auto md:mr-0 md:ml-auto md:min-h-screen flex flex-col justify-center">

                            <header class="mb-4 mt-3">
                                <div class="logo pb-4 mb-3 xl:mb-6">
                                    <img v-if="webinar.header_logo" :src="`/storage/${webinar.header_logo}`" alt="Logos" class="h-[60px] w-auto">
                                    <img v-else-if="client.logo" :src="`/storage/${client.logo}`" alt="Client Logo" class="h-[60px] w-auto">
                                </div>
                                <p v-if="webinar.subtitle" class="text-[#00B0D3] text-[24px] leading-[29px] mb-0 tracking-normal font-normal">
                                    {{ webinar.subtitle }}
                                </p>
                                <h1 class="text-[#041B36] font-bold text-[34px] md:text-[38px] leading-[1.1] mb-3 font-roboto">
                                    {{ webinar.title }}
                                </h1>
                                <div v-if="webinar.description" class="text-[#656668] text-[16px] mb-4 font-roboto" v-html="webinar.description"></div>
                            </header>

                            <!-- Dynamic Form Component -->
                            <div class="w-full">
                                <DynamicForm
                                    v-if="webinar.form_schema && webinar.form_schema.length"
                                    :schema="webinar.form_schema"
                                    :submit-url="submitUrl"
                                />
                                <div v-else class="text-center text-gray-500 py-4">
                                    Formulario no disponible.
                                </div>
                            </div>

                            <footer class="mt-8 md:mt-12 mb-8">
                                <div class="block mt-3">
                                    <p class="text-[12px] mb-2 text-[#656668]">Síguenos:</p>
                                    <div class="flex mb-4" v-if="socialLinks.length">
                                        <a v-for="link in socialLinks"
                                           :key="link.type"
                                           :href="link.url"
                                           target="_blank"
                                           class="mr-4 text-[#656668] hover:text-[#00B0D3] transition-all text-xl"
                                           :title="link.type">
                                            <i :class="getSocialIcon(link.type)"></i>
                                        </a>
                                    </div>
                                    <p class="text-[12px] text-[#656668] mb-1 mt-3 xl:mt-5">
                                        Copyright © | {{ new Date().getFullYear() }} {{ client.name }} | All rights reserved.
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
/* Font override utility */
.font-roboto {
    font-family: 'Roboto', sans-serif;
}
</style>
