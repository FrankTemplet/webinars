<script setup lang="ts">
import { generateUUID } from '@/lib/utils';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// Declare global window properties for tracking scripts
declare global {
    interface Window {
        fbq?: (
            action: string,
            event: string,
            params?: any,
            options?: { eventID?: string },
        ) => void;
        lintrk?: (action: string, params?: any) => void;
    }
}

type FieldType =
    | 'text'
    | 'email'
    | 'number'
    | 'tel'
    | 'textarea'
    | 'select'
    | 'checkbox'
    | 'radio'
    | 'multiselect';

interface FormField {
    name: string;
    type: FieldType;
    label: string;
    required?: boolean;
    options?: string[];
}

interface TrackingScript {
    platform: 'facebook' | 'linkedin';
    pixel_id?: string;
    partner_id?: string;
    conversion_id?: string;
    enabled: boolean;
}

interface Session {
    id: string;
    label: string;
}

interface Props {
    schema: FormField[];
    /** Sesiones del evento: cada una es un webinar de Zoom con su invitación. */
    sessions?: Session[];
    sessionsLabel?: string | null;
    submitUrl: string;
    submitLabel?: string | null;
    trackingScripts?: TrackingScript[];
    /** En el preview del panel el formulario se ve pero no se envía. */
    preview?: boolean;
}

interface FormData {
    [key: string]: string | boolean | string[];
}

const props = defineProps<Props>();

// Initialize form data based on schema with correct types
const formData: FormData = {};
props.schema.forEach((field) => {
    if (field.type === 'multiselect') {
        formData[field.name] = [];
    } else if (field.type === 'checkbox') {
        formData[field.name] = false;
    } else {
        formData[field.name] = '';
    }
});

if (props.sessions?.length) {
    formData.sessions = [];
}

// Capture UTM parameters from URL
const urlParams = new URLSearchParams(window.location.search);
const utmParams = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
] as const;

utmParams.forEach((param) => {
    const value = urlParams.get(param);
    if (value) {
        formData[param] = value;
    }
});

const form = useForm(formData);

const phoneInputs = ref<{ [key: string]: HTMLInputElement | null }>({});

// Función para formatear teléfono
const formatPhoneNumber = (value: string): string => {
    const cleaned = value.replace(/\D/g, '');

    if (cleaned.length <= 3) {
        return cleaned;
    } else if (cleaned.length <= 6) {
        return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3)}`;
    } else {
        return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6, 10)}`;
    }
};

// Manejar input de teléfono
const handlePhoneInput = (fieldName: string, event: Event): void => {
    const input = event.target as HTMLInputElement;
    const cursorPosition = input.selectionStart || 0;

    // Eliminar todo excepto números del input
    const numbersOnly = input.value.replace(/\D/g, '');

    // Si no hay números, limpiar el campo
    if (numbersOnly === '') {
        form[fieldName] = '';
        return;
    }

    // Formatear el valor
    const formattedValue = formatPhoneNumber(numbersOnly);
    form[fieldName] = formattedValue;

    // Calcular nueva posición del cursor
    // Contar cuántos números hay antes de la posición actual del cursor
    const numbersBeforeCursor = input.value
        .slice(0, cursorPosition)
        .replace(/\D/g, '').length;

    // Encontrar la posición en el nuevo valor formateado que corresponde a esa cantidad de números
    let newCursorPosition = 0;
    let numbersCount = 0;

    for (let i = 0; i < formattedValue.length; i++) {
        if (/\d/.test(formattedValue[i])) {
            numbersCount++;
            if (numbersCount === numbersBeforeCursor) {
                newCursorPosition = i + 1;
                break;
            }
        }
    }

    // Si no encontramos la posición exacta, colocar al final
    if (numbersCount < numbersBeforeCursor) {
        newCursorPosition = formattedValue.length;
    }

    setTimeout(() => {
        input.setSelectionRange(newCursorPosition, newCursorPosition);
    }, 0);
};

// Helper function to trigger Facebook Pixel conversion event
const triggerFacebookConversion = (eventId: string): void => {
    if (typeof window.fbq === 'function') {
        window.fbq('track', 'CompleteRegistration', {}, { eventID: eventId });
        console.log(
            `Facebook Pixel: CompleteRegistration event triggered. Event ID: ${eventId}`,
        );
    } else {
        console.warn('Facebook Pixel not loaded');
    }
};

// Helper function to trigger LinkedIn conversion event
const triggerLinkedInConversion = (conversionId: string): void => {
    if (typeof window.lintrk === 'function') {
        window.lintrk('track', { conversion_id: parseInt(conversionId) });
        console.log(
            `LinkedIn Insight Tag: Conversion ${conversionId} triggered`,
        );
    } else {
        console.warn('LinkedIn Insight Tag not loaded');
    }
};

// Trigger all enabled tracking events
const triggerTrackingEvents = (eventId: string): void => {
    if (!props.trackingScripts || props.trackingScripts.length === 0) {
        return;
    }

    props.trackingScripts.forEach((script) => {
        if (!script.enabled) return;

        if (script.platform === 'facebook' && script.pixel_id) {
            triggerFacebookConversion(eventId);
        } else if (script.platform === 'linkedin' && script.conversion_id) {
            triggerLinkedInConversion(script.conversion_id);
        }
    });
};

const submit = (): void => {
    if (props.preview) return;

    // Generate a unique event ID for de-duplication (CAPI)
    const eventId = generateUUID();

    // Add event_id to the form before sending to server
    form.event_id = eventId;

    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => {
            // Trigger tracking events on successful submission
            triggerTrackingEvents(eventId);
            // Reset form
            form.reset();
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="w-full">
        <div
            v-for="(field, index) in schema"
            :key="index"
            class="relative mb-3"
        >
            <div class="form-floating relative">
                <!-- Text / Email / Number -->
                <input
                    v-if="['text', 'email', 'number'].includes(field.type)"
                    :type="field.type"
                    :id="field.name"
                    :placeholder="field.label"
                    v-model="form[field.name]"
                    :required="field.required"
                    class="form-field peer block h-[58px] w-full px-3 pt-[1.625rem] pb-[0.625rem] placeholder-transparent"
                />

                <!-- Phone Input with Mask -->
                <input
                    v-else-if="field.type === 'tel'"
                    type="tel"
                    :id="field.name"
                    :ref="
                        (el) =>
                            (phoneInputs[field.name] = el as HTMLInputElement)
                    "
                    :placeholder="field.label"
                    :value="form[field.name]"
                    @input="handlePhoneInput(field.name, $event)"
                    @keypress="
                        (e) => {
                            if (
                                !/[0-9]/.test(e.key) &&
                                e.key !== 'Backspace' &&
                                e.key !== 'Delete' &&
                                e.key !== 'Tab' &&
                                e.key !== 'ArrowLeft' &&
                                e.key !== 'ArrowRight'
                            )
                                e.preventDefault();
                        }
                    "
                    :required="field.required"
                    maxlength="14"
                    class="form-field peer block h-[58px] w-full px-3 pt-[1.625rem] pb-[0.625rem] placeholder-transparent"
                />

                <!-- Textarea -->
                <textarea
                    v-else-if="field.type === 'textarea'"
                    :id="field.name"
                    v-model="form[field.name] as string"
                    :placeholder="field.label"
                    :required="field.required"
                    class="form-field peer block h-auto min-h-[100px] w-full px-3 pt-[1.625rem] pb-[0.625rem] placeholder-transparent"
                ></textarea>

                <!-- Select -->
                <select
                    v-else-if="field.type === 'select'"
                    :id="field.name"
                    v-model="form[field.name] as string"
                    :required="field.required"
                    class="form-field peer block h-[58px] w-full appearance-none px-3 pt-[1.625rem] pb-[0.625rem]"
                >
                    <option value="" disabled selected></option>
                    <option
                        v-for="option in field.options"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>

                <!-- Radio Button Group -->
                <fieldset
                    v-else-if="['radio', 'multiselect'].includes(field.type)"
                    class="pt-2"
                >
                    <legend
                        class="group-label mb-2 block text-sm font-semibold"
                    >
                        {{ field.label }}
                        <span v-if="field.required" class="text-red-500"
                            >*</span
                        >
                    </legend>
                    <div class="flex flex-row flex-wrap gap-x-6 gap-y-2">
                        <label
                            v-for="option in field.options"
                            :key="option"
                            :for="`${field.name}_${option}`"
                            class="flex items-center whitespace-nowrap"
                        >
                            <input
                                :id="`${field.name}_${option}`"
                                :type="
                                    field.type === 'multiselect'
                                        ? 'checkbox'
                                        : 'radio'
                                "
                                :name="field.name"
                                :value="option"
                                v-model="form[field.name]"
                                :required="
                                    field.type === 'radio' && field.required
                                "
                                class="form-check-input h-4 w-4 shrink-0 cursor-pointer"
                            />
                            <span class="ml-2 block cursor-pointer text-sm">
                                {{ option }}
                            </span>
                        </label>
                    </div>
                </fieldset>

                <!-- Checkbox (Custom styling) -->
                <div
                    v-else-if="field.type === 'checkbox'"
                    class="flex items-center pt-2"
                >
                    <input
                        :id="field.name"
                        type="checkbox"
                        v-model="form[field.name] as boolean"
                        class="form-check-input h-4 w-4 rounded"
                    />
                    <label :for="field.name" class="ml-2 block text-sm">
                        {{ field.label }}
                    </label>
                </div>

                <!-- Floating Label -->
                <label
                    v-if="
                        !['checkbox', 'radio', 'multiselect'].includes(
                            field.type,
                        )
                    "
                    :for="field.name"
                    class="pointer-events-none absolute top-0 left-0 h-full origin-[0_0] scale-100 transform px-3 py-4 transition-all duration-200 ease-in-out peer-not-placeholder-shown:-translate-y-2 peer-not-placeholder-shown:scale-85 peer-not-placeholder-shown:opacity-65 peer-focus:-translate-y-2 peer-focus:scale-85 peer-focus:opacity-65"
                >
                    {{ field.label }}
                </label>
            </div>

            <div
                v-for="(message, key) in Object.fromEntries(
                    Object.entries(form.errors).filter(([key]) =>
                        key.startsWith(field.name + '.'),
                    ),
                )"
                :key="key"
                role="alert"
                class="mt-1 text-sm text-red-600"
            >
                {{ message }}
            </div>
            <div
                v-if="form.errors[field.name]"
                class="mt-1 text-sm text-red-600"
            >
                {{ form.errors[field.name] }}
            </div>
        </div>

        <!-- Sesiones: una invitación de Zoom por cada una que elija -->
        <div v-if="sessions?.length" class="mb-3 pt-2">
            <label class="group-label mb-2 block text-sm font-semibold">
                {{ sessionsLabel || '¿A qué sesiones quieres asistir?' }}
                <span class="text-red-500">*</span>
            </label>
            <div class="flex flex-col gap-y-2">
                <div
                    v-for="session in sessions"
                    :key="session.id"
                    class="flex items-center"
                >
                    <input
                        :id="`sessions_${session.id}`"
                        type="checkbox"
                        :value="session.id"
                        v-model="form.sessions as string[]"
                        class="form-check-input h-4 w-4 cursor-pointer rounded"
                    />
                    <label
                        :for="`sessions_${session.id}`"
                        class="ml-2 block cursor-pointer text-sm"
                    >
                        {{ session.label }}
                    </label>
                </div>
            </div>
            <div v-if="form.errors.sessions" class="mt-1 text-sm text-red-600">
                {{ form.errors.sessions }}
            </div>
        </div>

        <div class="mt-4">
            <button
                type="submit"
                :disabled="form.processing"
                class="btn-submit inline-block w-auto cursor-pointer border px-[1.75rem] py-[0.625rem] text-center align-middle text-[1rem] leading-[1.5] transition-colors duration-150 ease-in-out select-none disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span v-if="form.processing">Enviando...</span>
                <span v-else>{{ submitLabel || 'Registrarme' }}</span>
            </button>
        </div>

        <div v-if="form.recentlySuccessful" class="mt-4 p-4 text-center">
            <h1 class="group-label mb-2 text-[28px] font-bold">Gracias!</h1>
            <p class="mb-0">Sus datos han sido ingresados con éxito.</p>
        </div>
    </form>
</template>

<style scoped>
/* Los colores y radios vienen de los tokens que la landing define en su div
   raíz (--accent, --text, --heading, --radius-field, --radius-button), con
   los valores originales del formulario como respaldo. */
.form-field {
    color: var(--text, #656668);
    background-color: var(--field-bg, #ffffff);
    border: 1px solid var(--field-border, #dadcde);
    border-radius: var(--radius-field, 5px);
}

.form-field:focus {
    outline: none;
    border-color: var(--accent, #00b0d3);
    box-shadow: 0 0 0 1px var(--accent, #00b0d3);
}

.group-label {
    color: var(--heading, #041b36);
}

.form-check-input {
    accent-color: var(--accent, #00b0d3);
    border-color: var(--field-border, #dadcde);
}

.btn-submit {
    color: var(--accent-text, #ffffff);
    background-color: var(--accent, #00b0d3);
    border-color: var(--accent, #00b0d3);
    border-radius: var(--radius-button, 0);
    text-transform: var(--button-transform, uppercase);
    font-weight: 700;
}

.btn-submit:hover:not(:disabled),
.btn-submit:focus-visible:not(:disabled) {
    color: var(--accent, #00b0d3);
    background-color: var(--bg, #ffffff);
    border-color: var(--accent, #00b0d3);
}
</style>
