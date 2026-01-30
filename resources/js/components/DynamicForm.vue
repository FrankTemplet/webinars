<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from "vue";

// Declare global window properties for tracking scripts
declare global {
    interface Window {
        fbq?: (action: string, event: string, params?: any) => void;
        lintrk?: (action: string, params?: any) => void;
    }
}


type FieldType = 'text' | 'email' | 'number' | 'tel' | 'textarea' | 'select' | 'checkbox';

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

interface Props {
    schema: FormField[];
    submitUrl: string;
    trackingScripts?: TrackingScript[];
}

interface FormData {
    [key: string]: string | boolean;
}

const props = defineProps<Props>();

// Initialize form data based on schema with correct types
const formData: FormData = {};
props.schema.forEach(field => {
    if (field.type === 'checkbox') {
        formData[field.name] = false;
    } else {
        formData[field.name] = '';
    }
});


// Capture UTM parameters from URL
const urlParams = new URLSearchParams(window.location.search);
const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as const;

utmParams.forEach(param => {
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
    const numbersBeforeCursor = input.value.slice(0, cursorPosition).replace(/\D/g, '').length;

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
const triggerFacebookConversion = (): void => {
    if (typeof window.fbq === 'function') {
        window.fbq('track', 'CompleteRegistration');
        console.log('Facebook Pixel: CompleteRegistration event triggered');
    } else {
        console.warn('Facebook Pixel not loaded');
    }
};

// Helper function to trigger LinkedIn conversion event
const triggerLinkedInConversion = (conversionId: string): void => {
    if (typeof window.lintrk === 'function') {
        window.lintrk('track', { conversion_id: parseInt(conversionId) });
        console.log(`LinkedIn Insight Tag: Conversion ${conversionId} triggered`);
    } else {
        console.warn('LinkedIn Insight Tag not loaded');
    }
};

// Trigger all enabled tracking events
const triggerTrackingEvents = (): void => {
    if (!props.trackingScripts || props.trackingScripts.length === 0) {
        return;
    }

    props.trackingScripts.forEach(script => {
        if (!script.enabled) return;

        if (script.platform === 'facebook' && script.pixel_id) {
            triggerFacebookConversion();
        } else if (script.platform === 'linkedin' && script.conversion_id) {
            triggerLinkedInConversion(script.conversion_id);
        }
    });
};

const submit = (): void => {
    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => {
            // Trigger tracking events on successful submission
            triggerTrackingEvents();
            // Reset form
            form.reset();
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="w-full">
        <div v-for="(field, index) in schema" :key="index" class="mb-3 relative">

            <div class="form-floating relative">
                <!-- Text / Email / Number -->
                <input
                    v-if="['text', 'email', 'number'].includes(field.type)"
                    :type="field.type"
                    :id="field.name"
                    :placeholder="field.label"
                    v-model="form[field.name]"
                    :required="field.required"
                    class="block w-full h-[58px] px-3 pt-[1.625rem] pb-[0.625rem] text-[#656668] bg-white border border-[#DADCDE] rounded-[5px] focus:outline-none focus:border-[#00B0D3] focus:ring-1 focus:ring-[#00B0D3] peer placeholder-transparent"
                />

                <!-- Phone Input with Mask -->
                <input
                    v-else-if="field.type === 'tel'"
                    type="tel"
                    :id="field.name"
                    :ref="(el) => phoneInputs[field.name] = el as HTMLInputElement"
                    :placeholder="field.label"
                    :value="form[field.name]"
                    @input="handlePhoneInput(field.name, $event)"
                    @keypress="(e) => { if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'Tab' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') e.preventDefault(); }"
                    :required="field.required"
                    maxlength="14"
                    class="block w-full h-[58px] px-3 pt-[1.625rem] pb-[0.625rem] text-[#656668] bg-white border border-[#DADCDE] rounded-[5px] focus:outline-none focus:border-[#00B0D3] focus:ring-1 focus:ring-[#00B0D3] peer placeholder-transparent"
                />

                <!-- Textarea -->
                <textarea
                    v-else-if="field.type === 'textarea'"
                    :id="field.name"
                    v-model="form[field.name] as string"
                    :placeholder="field.label"
                    :required="field.required"
                    class="block w-full px-3 pt-[1.625rem] pb-[0.625rem] text-[#656668] bg-white border border-[#DADCDE] rounded-[5px] focus:outline-none focus:border-[#00B0D3] focus:ring-1 focus:ring-[#00B0D3] peer placeholder-transparent h-auto min-h-[100px]"
                ></textarea>

                <!-- Select -->
                <select
                    v-else-if="field.type === 'select'"
                    :id="field.name"
                    v-model="form[field.name] as string"
                    :required="field.required"
                    class="block w-full h-[58px] px-3 pt-[1.625rem] pb-[0.625rem] text-[#656668] bg-white border border-[#DADCDE] rounded-[5px] focus:outline-none focus:border-[#00B0D3] focus:ring-1 focus:ring-[#00B0D3] peer appearance-none"
                >
                    <option value="" disabled selected></option>
                    <option v-for="option in field.options" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>

                <!-- Checkbox (Custom styling) -->
                <div v-else-if="field.type === 'checkbox'" class="flex items-center pt-2">
                    <input
                        :id="field.name"
                        type="checkbox"
                        v-model="form[field.name] as boolean"
                        class="h-4 w-4 rounded border-gray-300 text-[#00B0D3] focus:ring-[#00B0D3]"
                    />
                    <label :for="field.name" class="ml-2 block text-sm text-[#656668]">
                        {{ field.label }}
                    </label>
                </div>

                <!-- Floating Label -->
                <label
                    v-if="!['checkbox'].includes(field.type)"
                    :for="field.name"
                    class="absolute top-0 left-0 h-full px-3 py-4 text-[#656668] transition-all duration-200 ease-in-out origin-[0_0] transform scale-100 pointer-events-none peer-focus:scale-85 peer-focus:-translate-y-2 peer-not-placeholder-shown:scale-85 peer-not-placeholder-shown:-translate-y-2 peer-focus:opacity-65 peer-not-placeholder-shown:opacity-65"
                >
                    {{ field.label }}
                </label>
            </div>

            <div v-if="form.errors[field.name]" class="text-sm text-red-600 mt-1">
                {{ form.errors[field.name] }}
            </div>
        </div>

        <div class="mt-4">
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-block w-auto text-center align-middle cursor-pointer select-none border border-transparent px-[1.75rem] py-[0.625rem] text-[1rem] leading-[1.5] rounded-none uppercase font-bold text-white bg-[#00B0D3] hover:bg-white hover:text-[#00B0D3] hover:border-[#00B0D3] transition-colors duration-150 ease-in-out disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span v-if="form.processing">Enviando...</span>
                <span v-else>Registrarme</span>
            </button>
        </div>

        <div v-if="form.recentlySuccessful" class="mt-4 p-4 text-center">
            <h1 class="mb-2 text-[28px] font-bold text-[#041B36]">Gracias!</h1>
            <p class="mb-0 text-[#656668]">Sus datos han sido ingresados con éxito.</p>
        </div>
    </form>
</template>
