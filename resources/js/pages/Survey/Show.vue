<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface SocialMediaLink {
    type: string;
    url: string;
}

interface Client {
    slug: string;
    name: string;
    logo?: string;
    footer?: string | null;
    social_media?: SocialMediaLink[];
}

interface ExtraQuestion {
    number: number;
    type: 'text' | 'textarea' | 'number' | 'select' | 'radio' | 'checkbox' | 'rating';
    name: string;
    label: string;
    required: boolean;
    options: string[];
}

interface Survey {
    slug: string;
    title: string;
    subtitle?: string;
    intro?: string;
    hero_image?: string;
    header_logo?: string;
    accent_color: string;
    accent_text_color: string;
    meta_title?: string;
    meta_description?: string;
    is_open: boolean;
    closed_message?: string;
    thank_you_title?: string;
    thank_you_message?: string;
    guests_count: number;
    contact_enabled: boolean;
    questions: { q1: string; q2: string; q3: string; q4: string; q5: string };
    stage_options: string[];
    extra_questions: ExtraQuestion[];
}

interface Props {
    client: Client;
    survey: Survey;
    submitUrl: string;
}

const props = defineProps<Props>();

const guestRows = computed(() => Array.from({ length: props.survey.guests_count }, (_, i) => i));

// Las preguntas extra viven bajo `extra` para no chocar con los campos fijos.
const extraDefaults: Record<string, string | number | boolean> = {};
props.survey.extra_questions.forEach((question) => {
    extraDefaults[question.name] = question.type === 'checkbox' ? false : question.type === 'rating' ? 5 : '';
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    extra: extraDefaults,
    experience_rating: 5 as number,
    use_case: '',
    stage: props.survey.stage_options[0] ?? '',
    wants_review: true,
    guests: Array.from({ length: props.survey.guests_count }, () => ({ name: '', email: '', topics: '' })),
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
    utm_term: '',
    utm_content: '',
});

// Arrastra los UTM de la URL para atribuir la respuesta igual que un registro.
const urlParams = new URLSearchParams(window.location.search);
(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as const).forEach((param) => {
    const value = urlParams.get(param);
    if (value) form[param] = value;
});

// Tokens de marca del original (SASE usa naranja, gen-IA verde).
const brandVars = computed(() => ({
    '--accent': props.survey.accent_color,
    '--accent-text': props.survey.accent_text_color,
}));

// Los errores de campos anidados (guests.0.email) y los de la encuesta en sí
// no están en el tipo del form, así que se leen por llave.
const errorFor = (key: string): string | undefined =>
    (form.errors as Record<string, string | undefined>)[key];

// Las cinco de la plantilla siempre están; solo la de invitados puede
// ocultarse, así que la numeración se toma del servidor para las extra.
const guestQuestionNumber = 5;

const socialLinks = computed(() => props.client.social_media || (props.client as any).socialMedia || []);

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
    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="survey.meta_title || survey.title">
        <meta name="description" :content="survey.meta_description || ''" />
        <link v-if="client.logo" rel="icon" type="image/x-icon" :href="`/storage/${client.logo}`" />
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=maven-pro:400,500,600,700,800" />
    </Head>

    <div class="survey-page" :style="brandVars">
        <!-- Header: solo la imagen de portada, como en el original -->
        <header>
            <div
                class="cover header-cover"
                :class="survey.hero_image ? '' : 'header-cover--empty'"
                :style="survey.hero_image ? `background-image: url('/storage/${survey.hero_image}');` : ''"
            >
                <img v-if="survey.header_logo" :src="`/storage/${survey.header_logo}`" alt="Logo" class="header-logo" />
            </div>
        </header>

        <!-- Intro -->
        <main class="py-6">
            <div class="survey-container">
                <h1 class="title mb-3">{{ survey.title }}</h1>
                <p v-if="survey.subtitle" class="lead lead--strong mb-3">{{ survey.subtitle }}</p>
                <div v-if="survey.intro" class="lead survey-intro" v-html="survey.intro"></div>
            </div>
        </main>

        <div class="survey-container">
            <hr />
        </div>

        <!-- Encuesta cerrada -->
        <section v-if="!survey.is_open" class="survey-container state-block">
            <p class="lead">
                {{ survey.closed_message || 'Esta encuesta ya no acepta respuestas. ¡Gracias por tu interés!' }}
            </p>
        </section>

        <!-- Agradecimiento -->
        <section v-else-if="form.recentlySuccessful" class="survey-container state-block">
            <h2 class="title mb-3">{{ survey.thank_you_title || '¡Gracias por tu respuesta!' }}</h2>
            <div
                class="lead"
                v-html="survey.thank_you_message || 'Tu retroalimentación nos ayuda a mejorar las próximas sesiones.'"
            ></div>
        </section>

        <form v-else @submit.prevent="submit" novalidate>
            <!-- Datos de contacto -->
            <section v-if="survey.contact_enabled" class="pt-5 mb-4">
                <div class="survey-container">
                    <div class="box-card">
                        <p class="title-box mb-1">Déjanos tus datos</p>
                        <p class="lead mb-3">Los usamos únicamente para dar seguimiento a tus respuestas.</p>
                        <div class="field-row">
                            <div class="field">
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="form-control"
                                    placeholder="Nombre completo"
                                    autocomplete="name"
                                />
                                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                            </div>
                            <div class="field">
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="form-control"
                                    placeholder="Correo electrónico"
                                    autocomplete="email"
                                />
                                <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                            </div>
                            <div class="field">
                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    class="form-control"
                                    placeholder="Celular"
                                    autocomplete="tel"
                                />
                                <p v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 1. Experiencia -->
            <section class="pt-5 mb-4" :class="{ 'pt-5': !survey.contact_enabled }">
                <div class="survey-container">
                    <div class="box-card">
                        <ol start="1">
                            <li class="title-box">{{ survey.questions.q1 }}</li>
                        </ol>
                        <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="form-check">
                            <input
                                :id="`experiencia${star}`"
                                v-model="form.experience_rating"
                                class="form-check-input"
                                type="radio"
                                name="experienciaSesion"
                                :value="star"
                            />
                            <label class="form-check-label stars" :for="`experiencia${star}`">
                                <svg v-for="i in star" :key="i" class="star" viewBox="0 0 24 24" aria-hidden="true">
                                    <path
                                        d="M12 3.2l2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.5l-5.2 2.7 1-5.75-4.2-4.1 5.8-.85L12 3.2z"
                                    />
                                </svg>
                                <span class="visually-hidden">{{ star }} de 5</span>
                            </label>
                        </div>
                        <p v-if="form.errors.experience_rating" class="field-error">
                            {{ form.errors.experience_rating }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- 2. Caso de uso -->
            <section class="mb-4">
                <div class="survey-container">
                    <div class="box-card">
                        <ol start="2">
                            <li class="title-box">{{ survey.questions.q2 }}</li>
                        </ol>
                        <textarea v-model="form.use_case" class="form-control" rows="3"></textarea>
                        <p v-if="form.errors.use_case" class="field-error">{{ form.errors.use_case }}</p>
                    </div>
                </div>
            </section>

            <!-- 3. Etapa -->
            <section class="mb-4">
                <div class="survey-container">
                    <div class="box-card">
                        <ol start="3">
                            <li class="title-box">{{ survey.questions.q3 }}</li>
                        </ol>
                        <div v-for="option in survey.stage_options" :key="option" class="form-check">
                            <input
                                :id="`etapa_${option}`"
                                v-model="form.stage"
                                class="form-check-input"
                                type="radio"
                                name="etapaRadio"
                                :value="option"
                            />
                            <label class="form-check-label" :for="`etapa_${option}`">{{ option }}</label>
                        </div>
                        <p v-if="form.errors.stage" class="field-error">{{ form.errors.stage }}</p>
                    </div>
                </div>
            </section>

            <!-- 4. Revisión personalizada -->
            <section class="mb-4">
                <div class="survey-container">
                    <div class="box-card">
                        <ol start="4">
                            <li class="title-box">{{ survey.questions.q4 }}</li>
                        </ol>
                        <div class="form-check">
                            <input
                                id="revisionSi"
                                v-model="form.wants_review"
                                class="form-check-input"
                                type="radio"
                                name="quiereRevision"
                                :value="true"
                            />
                            <label class="form-check-label" for="revisionSi">Si</label>
                        </div>
                        <div class="form-check">
                            <input
                                id="revisionNo"
                                v-model="form.wants_review"
                                class="form-check-input"
                                type="radio"
                                name="quiereRevision"
                                :value="false"
                            />
                            <label class="form-check-label" for="revisionNo">No</label>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. Invitados -->
            <section v-if="survey.guests_count > 0" class="mb-5">
                <div class="survey-container">
                    <div class="box-card">
                        <ol :start="guestQuestionNumber">
                            <li class="title-box">{{ survey.questions.q5 }}</li>
                        </ol>
                        <div v-for="i in guestRows" :key="i" class="field-row mb-3">
                            <input
                                v-model="form.guests[i].name"
                                type="text"
                                class="form-control"
                                placeholder="Nombre completo"
                            />
                            <input
                                v-model="form.guests[i].email"
                                type="email"
                                class="form-control"
                                placeholder="Correo electrónico"
                            />
                            <input
                                v-model="form.guests[i].topics"
                                type="text"
                                class="form-control"
                                placeholder="Temas de interés"
                            />
                            <p v-if="errorFor(`guests.${i}.email`)" class="field-error field-error--row">
                                {{ errorFor(`guests.${i}.email`) }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Preguntas adicionales configuradas para esta encuesta -->
            <section v-for="question in survey.extra_questions" :key="question.name" class="mb-4">
                <div class="survey-container">
                    <div class="box-card">
                        <ol :start="question.number">
                            <li class="title-box">
                                {{ question.label }}<span v-if="question.required" class="required">*</span>
                            </li>
                        </ol>

                        <input
                            v-if="['text', 'number'].includes(question.type)"
                            :id="`extra_${question.name}`"
                            :type="question.type"
                            v-model="form.extra[question.name]"
                            class="form-control"
                        />

                        <textarea
                            v-else-if="question.type === 'textarea'"
                            :id="`extra_${question.name}`"
                            v-model="form.extra[question.name] as string"
                            class="form-control"
                            rows="3"
                        ></textarea>

                        <select
                            v-else-if="question.type === 'select'"
                            :id="`extra_${question.name}`"
                            v-model="form.extra[question.name] as string"
                            class="form-control form-select"
                        >
                            <option value="" disabled>Selecciona una opción</option>
                            <option v-for="option in question.options" :key="option" :value="option">
                                {{ option }}
                            </option>
                        </select>

                        <template v-else-if="question.type === 'radio'">
                            <div v-for="option in question.options" :key="option" class="form-check">
                                <input
                                    :id="`extra_${question.name}_${option}`"
                                    v-model="form.extra[question.name] as string"
                                    class="form-check-input"
                                    type="radio"
                                    :name="`extra_${question.name}`"
                                    :value="option"
                                />
                                <label class="form-check-label" :for="`extra_${question.name}_${option}`">
                                    {{ option }}
                                </label>
                            </div>
                        </template>

                        <div v-else-if="question.type === 'checkbox'" class="form-check">
                            <input
                                :id="`extra_${question.name}`"
                                v-model="form.extra[question.name] as boolean"
                                class="form-check-input"
                                type="checkbox"
                            />
                            <label class="form-check-label" :for="`extra_${question.name}`">Si</label>
                        </div>

                        <template v-else-if="question.type === 'rating'">
                            <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="form-check">
                                <input
                                    :id="`extra_${question.name}_${star}`"
                                    v-model="form.extra[question.name]"
                                    class="form-check-input"
                                    type="radio"
                                    :name="`extra_${question.name}`"
                                    :value="star"
                                />
                                <label class="form-check-label stars" :for="`extra_${question.name}_${star}`">
                                    <svg v-for="i in star" :key="i" class="star" viewBox="0 0 24 24" aria-hidden="true">
                                        <path
                                            d="M12 3.2l2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.5l-5.2 2.7 1-5.75-4.2-4.1 5.8-.85L12 3.2z"
                                        />
                                    </svg>
                                    <span class="visually-hidden">{{ star }} de 5</span>
                                </label>
                            </div>
                        </template>

                        <p v-if="errorFor(`extra.${question.name}`)" class="field-error">
                            {{ errorFor(`extra.${question.name}`) }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="mb-5 pb-5">
                <div class="survey-container text-center">
                    <p v-if="errorFor('survey')" class="field-error mb-3">{{ errorFor('survey') }}</p>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Enviando...' : 'Enviar' }}
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
                    {{ client.footer || `Copyright © ${new Date().getFullYear()} - ${client.name} | Todos los derechos reservados.` }}
                </span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Réplica de scss/_utilities, _forms, _buttons y _footer de las encuestas
   estáticas. Los colores de marca entran por --accent / --accent-text. */
.survey-page {
    font-family: 'Maven Pro', sans-serif;
    background-color: #ffffff;
    color: #1a1a1a;
}

/* .container de Bootstrap 5 */
.survey-container {
    width: 100%;
    margin: 0 auto;
    padding: 0 0.75rem;
}
@media (min-width: 576px) { .survey-container { max-width: 540px; } }
@media (min-width: 768px) { .survey-container { max-width: 720px; } }
@media (min-width: 992px) { .survey-container { max-width: 960px; } }
@media (min-width: 1200px) { .survey-container { max-width: 1140px; } }
@media (min-width: 1400px) { .survey-container { max-width: 1320px; } }

.cover {
    background-position: center center;
    background-repeat: no-repeat;
    background-size: cover;
}

/* La portada suele traer la marca quemada; cuando se sube un logo aparte
   va arriba y centrado, como la navbar del original. */
.header-cover {
    height: 45vh;
    min-height: 260px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 1.5rem;
}
.header-cover--empty { background-color: #f4f4f4; }
.header-logo { max-width: 100%; max-height: 70px; width: auto; }

.py-6 { padding-top: 6rem; padding-bottom: 6rem; }
.pt-5 { padding-top: 3rem; }
.mb-1 { margin-bottom: 0.25rem; }
.mb-3 { margin-bottom: 1rem; }
.mb-4 { margin-bottom: 1.5rem; }
.mb-5 { margin-bottom: 3rem; }
.pb-5 { padding-bottom: 3rem; }
.text-center { text-align: center; }

.title {
    color: #020e1e;
    font-size: 25px;
    line-height: 1.1;
    font-weight: 800;
}

.lead {
    color: #1a1a1a;
    font-size: 1rem;
    margin: 0;
}
.lead--strong { font-weight: 700; }
.survey-intro :deep(p) { margin: 0 0 1rem; }
.survey-intro :deep(p:last-child) { margin-bottom: 0; }

hr {
    opacity: 1;
    color: #f4f4f4;
    border: 0;
    border-top: 1px solid #f4f4f4;
    margin: 0;
}

.state-block { padding: 4rem 0.75rem; text-align: center; }

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
ol li::marker { font-weight: 700; color: #1a1a1a; }

.title-box {
    font-size: 16px;
    line-height: 1.1;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 1rem;
}
.required { color: #dc3545; margin-left: 0.25rem; }

.form-control {
    display: block;
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    font-family: inherit;
    color: #1a1a1a;
    background: #f2f2f280;
    border: 1px solid #f4f4f4;
    border-radius: 10px;
}
.form-control::placeholder { color: #999999; opacity: 1; }
.form-control:focus {
    outline: 0;
    border-color: var(--accent);
    box-shadow: 0 0 0 0.25rem color-mix(in srgb, var(--accent) 25%, transparent);
}
.form-select { appearance: none; }

.field-row { display: flex; gap: 1rem; width: 100%; }
.field { flex: 1 1 0; min-width: 0; }
@media (max-width: 767.98px) {
    .field-row { flex-direction: column; gap: 0.5rem; }
}

.field-error { color: #dc3545; font-size: 0.875rem; margin: 0.25rem 0 0; }
.field-error--row { flex-basis: 100%; }

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
.form-check-label { cursor: pointer; }

.stars { display: inline-flex; align-items: center; gap: 2px; }
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
    border-radius: 0.5rem;
}
.btn-primary {
    color: var(--accent-text);
    background-color: var(--accent);
    border-color: var(--accent);
    padding: 0.625rem 2rem;
    font-weight: 700;
    transition: filter 0.15s ease-in-out;
}
.btn-primary:hover:not(:disabled) { filter: brightness(0.94); }
.btn-primary:disabled { opacity: 0.65; cursor: not-allowed; }

.bg-footer { background-color: #f4f4f4; }
.footer-inner { padding: 1.5rem 0.75rem; text-align: center; }
.footer-social { margin-bottom: 0.75rem; }
.footer-social a {
    margin: 0 0.5rem;
    font-size: 1.25rem;
    color: #aaaaaa;
    transition: color 0.15s ease-in-out;
}
.footer-social a:hover { color: var(--accent); }
footer .text-muted { color: #1a1a1a; font-size: 0.75rem; }
</style>
