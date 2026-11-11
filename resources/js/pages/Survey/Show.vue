<script setup lang="ts">
import SurveyFieldInput from '@/components/SurveyField.vue';
import {
    toList,
    useLivePreview,
    type Theme,
} from '@/composables/useLivePreview';
import {
    initialAnswer,
    type SurveyAnswer,
    type SurveyField,
} from '@/types/survey';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface SocialMediaLink {
    type: string;
    url: string;
}

interface Client {
    slug: string;
    name: string;
    logo?: string;
    favicon?: string;
    footer?: string | null;
    social_media?: SocialMediaLink[];
}

interface SurveyCopy {
    contact_title: string;
    contact_description: string;
    submit_label: string;
    yes_label: string;
    no_label: string;
}

interface Survey {
    slug: string;
    intro?: string;
    hero_image?: string;
    mobile_hero_image?: string;
    question_schema: SurveyField[];
    contact_schema: SurveyField[];
    header_logo?: string;
    theme: Theme;
    meta_title?: string;
    meta_description?: string;
    is_open: boolean;
    closed_message?: string;
    thank_you_title?: string;
    thank_you_message?: string;
    guests_count: number;
    contact_enabled: boolean;
    copy: SurveyCopy;
    questions: { q1: string; q2: string; q3: string; q4: string; q5: string };
    stage_options: string[];
}

interface Props {
    client: Client;
    survey: Survey;
    submitUrl: string;
    /** Dentro del iframe del panel: los cambios del formulario llegan en vivo. */
    preview?: boolean;
}

const props = defineProps<Props>();

const {
    draft,
    imageUrl,
    pick,
    vars: brandVars,
    fontUrl,
} = useLivePreview(() => props.survey.theme, props.preview === true);

const view = computed(() => ({
    intro: pick('intro', props.survey.intro),
    contact_enabled:
        draft.value?.contact_enabled ?? props.survey.contact_enabled,
    contact_title: pick('contact_title', props.survey.copy.contact_title),
    contact_description: pick(
        'contact_description',
        props.survey.copy.contact_description,
    ),
    submit_label: pick('submit_label', props.survey.copy.submit_label),
    favicon:
        imageUrl('favicon', props.client.favicon) ||
        imageUrl('logo', props.client.logo),
    hero: imageUrl('hero_image', props.survey.hero_image),
    mobile: imageUrl('mobile_hero_image', props.survey.mobile_hero_image),
    logo: imageUrl('header_logo', props.survey.header_logo),
}));
const questions = computed(() =>
    draft.value && 'question_schema' in draft.value
        ? toList<SurveyField>(draft.value.question_schema)
        : props.survey.question_schema,
);
const contactFields = computed(() =>
    (draft.value && 'contact_schema' in draft.value
        ? toList<SurveyField>(draft.value.contact_schema)
        : props.survey.contact_schema
    ).filter((field) => !field.hidden),
);
const defaults = (fields: SurveyField[]) =>
    Object.fromEntries(
        fields.map((field) => [field.name, initialAnswer(field)]),
    );
const form = useForm({
    answers: defaults(props.survey.question_schema) as Record<
        string,
        SurveyAnswer
    >,
    contact: defaults(props.survey.contact_schema) as Record<
        string,
        SurveyAnswer
    >,
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
    utm_term: '',
    utm_content: '',
});
watch([questions, contactFields], () => {
    if (!props.preview) return;
    form.answers = defaults(questions.value);
    form.contact = defaults(contactFields.value);
});
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
// Arrastra los UTM de la URL para atribuir la respuesta igual que un registro.
const urlParams = new URLSearchParams(window.location.search);
(
    [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ] as const
).forEach((param) => {
    const value = urlParams.get(param);
    if (value) form[param] = value;
});

const socialLinks = computed(() => props.client.social_media ?? []);

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

const submit = (): void => {
    if (props.preview) return;

    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="survey.meta_title || client.name">
        <meta name="description" :content="survey.meta_description || ''" />
        <link v-if="view.favicon" rel="icon" :href="view.favicon" />
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link v-if="fontUrl" rel="stylesheet" :href="fontUrl" />
    </Head>

    <div class="survey-page" :style="brandVars">
        <!-- Header: solo la imagen de portada, como en el original -->
        <header>
            <div
                class="cover header-cover"
                :class="view.hero || view.mobile ? '' : 'header-cover--empty'"
                :style="{
                    '--hero-image': view.hero ? `url(${view.hero})` : 'none',
                    '--mobile-hero-image': view.mobile
                        ? `url(${view.mobile})`
                        : undefined,
                }"
            >
                <img
                    v-if="view.logo"
                    :src="view.logo"
                    alt="Logo"
                    class="header-logo"
                />
            </div>
        </header>

        <!-- Intro -->
        <main class="py-6">
            <div class="survey-container">
                <div
                    v-if="view.intro"
                    class="survey-intro"
                    v-html="view.intro"
                ></div>
            </div>
        </main>

        <div class="survey-container">
            <hr />
        </div>

        <!-- Encuesta cerrada -->
        <section v-if="!survey.is_open" class="survey-container state-block">
            <p class="lead">
                {{
                    survey.closed_message ||
                    'Esta encuesta ya no acepta respuestas. ¡Gracias por tu interés!'
                }}
            </p>
        </section>

        <!-- Agradecimiento -->
        <section
            v-else-if="form.recentlySuccessful"
            class="survey-container state-block"
        >
            <h2 class="title mb-3">
                {{ survey.thank_you_title || '¡Gracias por tu respuesta!' }}
            </h2>
            <div
                class="lead"
                v-html="
                    survey.thank_you_message ||
                    'Tu retroalimentación nos ayuda a mejorar las próximas sesiones.'
                "
            ></div>
        </section>

        <form v-else @submit.prevent="submit" novalidate>
            <section v-if="view.contact_enabled" class="mb-4 pt-5">
                <div class="survey-container">
                    <div class="box-card">
                        <p class="title-box mb-1">{{ view.contact_title }}</p>
                        <p class="lead contact-description">
                            {{ view.contact_description }}
                        </p>
                        <div class="field-row">
                            <div
                                v-for="field in contactFields"
                                :key="field.name"
                                class="field"
                            >
                                <label :for="`contact.${field.name}`"
                                    >{{ field.label
                                    }}<span
                                        v-if="field.required"
                                        class="required"
                                        >*</span
                                    ></label
                                >
                                <SurveyFieldInput
                                    v-model="form.contact[field.name]"
                                    :field="field"
                                    :id="`contact.${field.name}`"
                                    :errors="errors"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section
                v-for="(question, index) in questions"
                :key="question.name"
                class="mb-4"
            >
                <div class="survey-container">
                    <div class="box-card">
                        <ol :start="index + 1">
                            <li class="title-box">
                                {{ question.label
                                }}<span
                                    v-if="question.required"
                                    class="required"
                                    >*</span
                                >
                            </li>
                        </ol>
                        <SurveyFieldInput
                            v-model="form.answers[question.name]"
                            :field="question"
                            :id="`answers.${question.name}`"
                            :errors="errors"
                        />
                    </div>
                </div>
            </section>

            <section class="pb-5">
                <div class="survey-container text-center">
                    <p v-if="errors.survey" class="field-error mb-3">
                        {{ errors.survey }}
                    </p>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing ? 'Enviando...' : view.submit_label
                        }}
                    </button>
                </div>
            </section>
        </form>

        <footer class="bg-footer">
            <div class="survey-container footer-inner">
                <div v-if="socialLinks.length" class="footer-social">
                    <a
                        v-for="link in socialLinks"
                        :key="link.type"
                        :href="link.url"
                        target="_blank"
                        :title="link.type"
                    >
                        <i :class="getSocialIcon(link.type)"></i>
                    </a>
                </div>
                <span class="text-muted">
                    {{
                        client.footer ||
                        `Copyright © ${new Date().getFullYear()} - ${client.name} | Todos los derechos reservados.`
                    }}
                </span>
            </div>
        </footer>
    </div>
</template>

<style>
/* Réplica de scss/_utilities, _forms, _buttons y _footer de las encuestas
   estáticas. Los colores de marca entran por --accent / --accent-text. */
.survey-page {
    font-family: var(--font);
    background-color: var(--bg);
    color: var(--text);
}

/* .container de Bootstrap 5 */
.survey-container {
    width: 100%;
    margin: 0 auto;
    padding: 0 0.75rem;
}
@media (min-width: 576px) {
    .survey-container {
        max-width: 540px;
    }
}
@media (min-width: 768px) {
    .survey-container {
        max-width: 720px;
    }
}
@media (min-width: 992px) {
    .survey-container {
        max-width: 960px;
    }
}
@media (min-width: 1200px) {
    .survey-container {
        max-width: 1140px;
    }
}
@media (min-width: 1400px) {
    .survey-container {
        max-width: 1320px;
    }
}

.cover {
    background-position: center center;
    background-repeat: no-repeat;
    background-size: cover;
}

/* La portada suele traer la marca quemada; cuando se sube un logo aparte
   va arriba y centrado, como la navbar del original. */
.header-cover {
    background-image: var(--hero-image);
    height: 45vh;
    min-height: 260px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 1.5rem;
}
.header-cover--empty {
    background-color: #f4f4f4;
}
.header-logo {
    max-width: 100%;
    max-height: 70px;
    width: auto;
}

.py-6 {
    padding-top: 2rem;
    padding-bottom: 2rem;
}
.pt-5 {
    padding-top: 3rem;
}
.mb-1 {
    margin-bottom: 0.25rem;
}
.mb-3 {
    margin-bottom: 1rem;
}
.mb-4 {
    margin-bottom: 1.5rem;
}
.mb-5 {
    margin-bottom: 3rem;
}
.pb-5 {
    padding-bottom: 3rem;
}
.text-center {
    text-align: center;
}

.title {
    color: var(--heading);
    font-size: 25px;
    line-height: 1.1;
    font-weight: 800;
}

.lead {
    color: var(--text);
    font-size: 1rem;
    margin: 0;
}
.survey-intro :deep(h1),
.survey-intro :deep(h2),
.survey-intro :deep(h3) {
    color: var(--heading);
    font-size: 25px;
    line-height: 1.1;
    font-weight: 800;
    margin: 0 0 1rem;
}
.survey-intro :deep(p) {
    color: var(--text);
    font-size: 1rem;
    margin: 0 0 1rem;
}
.survey-intro :deep(p:last-child) {
    margin-bottom: 0;
}
.survey-intro :deep(ul),
.survey-intro :deep(ol) {
    margin: 0 0 1rem;
}
.survey-intro :deep(a) {
    color: var(--accent);
}

hr {
    opacity: 1;
    color: #f4f4f4;
    border: 0;
    border-top: 1px solid #f4f4f4;
    margin: 0;
}

.state-block {
    padding: 4rem 0.75rem;
    text-align: center;
}

.box-card {
    border: 1px solid #f4f4f4;
    border-radius: 18px;
    padding: 25px;
}

/* Tailwind preflight quita los marcadores; el original numera con <ol>. */
ol {
    padding-left: 1.5rem;
    margin: 0;
    list-style: decimal outside;
}
ol li::marker {
    font-weight: 700;
    color: var(--text);
}

.title-box {
    font-size: 16px;
    line-height: 1.1;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 1rem;
}
.required {
    color: #dc3545;
    margin-left: 0.25rem;
}

.form-control {
    display: block;
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    font-family: inherit;
    color: var(--text);
    background: #f2f2f280;
    border: 1px solid #f4f4f4;
    border-radius: var(--radius-field);
}
.form-control::placeholder {
    color: #999999;
    opacity: 1;
}
.form-control:focus {
    outline: 0;
    border-color: var(--accent);
    box-shadow: 0 0 0 0.25rem color-mix(in srgb, var(--accent) 25%, transparent);
}
.form-select {
    appearance: none;
}

.field-row {
    display: flex;
    gap: 1rem;
    width: 100%;
}
.field {
    flex: 1 1 0;
    min-width: 0;
}
@media (max-width: 767.98px) {
    .field-row {
        flex-direction: column;
        gap: 0.5rem;
    }
}

.field-error {
    color: #dc3545;
    font-size: 0.875rem;
    margin: 0.25rem 0 0;
}
.field-error--row {
    flex-basis: 100%;
}

.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}
.form-check-input {
    width: 1em;
    height: 1em;
    margin: 0 0.5rem 0 0;
    accent-color: var(--accent);
    cursor: pointer;
}
.form-check-input:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 0.25rem color-mix(in srgb, var(--accent) 25%, transparent);
}
.form-check-label {
    cursor: pointer;
}

.stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}
.star {
    width: 24px;
    height: 24px;
    fill: none;
    stroke: #aaaaaa;
    stroke-width: 1.5;
    stroke-linejoin: round;
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
}

.btn {
    display: inline-block;
    line-height: 1.5;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    font-family: inherit;
    font-size: 1rem;
    border: 1px solid transparent;
    border-radius: var(--radius-button);
}
.btn-primary {
    color: var(--accent-text);
    background-color: var(--accent);
    border-color: var(--accent);
    padding: 0.625rem 2rem;
    font-weight: 700;
    transition: filter 0.15s ease-in-out;
}
.btn-primary:hover:not(:disabled) {
    filter: brightness(0.94);
}
.btn-primary:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.bg-footer {
    background-color: #f4f4f4;
}
.footer-inner {
    padding: 1.5rem 0.75rem;
    text-align: center;
}
.footer-social {
    margin-bottom: 0.75rem;
}
.footer-social a {
    margin: 0 0.5rem;
    font-size: 1.25rem;
    color: #aaaaaa;
    transition: color 0.15s ease-in-out;
}
.footer-social a:hover {
    color: var(--accent);
}
footer .text-muted {
    color: var(--text);
    font-size: 0.75rem;
}
.contact-description {
    margin-bottom: 0.5rem;
}
@media (max-width: 767px) {
    .header-cover {
        background-image: var(--mobile-hero-image, var(--hero-image));
    }
}
</style>
