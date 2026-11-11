<script setup lang="ts">
import type { GuestAnswer, SurveyAnswer, SurveyField } from '@/types/survey';
const props = defineProps<{
    field: SurveyField;
    id: string;
    placeholder?: string;
    errors: Record<string, string | undefined>;
}>();
const model = defineModel<SurveyAnswer>({ required: true });
const updateGuest = (index: number, key: keyof GuestAnswer, event: Event) => {
    const guests = model.value as GuestAnswer[];
    model.value = guests.map((guest, i) =>
        i === index
            ? { ...guest, [key]: (event.target as HTMLInputElement).value }
            : guest,
    );
};
const error = (suffix = '') => props.errors[props.id + suffix];
</script>

<template>
    <div class="survey-field">
        <input
            v-if="['text', 'email', 'tel', 'number'].includes(field.type)"
            :id="id"
            v-model="model"
            :type="field.type"
            :aria-label="field.label"
            :placeholder="placeholder"
            :required="field.required"
            :aria-invalid="!!error()"
            class="form-control"
        />
        <textarea
            v-else-if="field.type === 'textarea'"
            :id="id"
            v-model="model as string"
            :aria-label="field.label"
            :placeholder="placeholder"
            :required="field.required"
            class="form-control"
            rows="3"
        ></textarea>
        <select
            v-else-if="field.type === 'select'"
            :id="id"
            v-model="model as string"
            :aria-label="field.label"
            :required="field.required"
            class="form-control"
        >
            <option value="" disabled>
                {{ placeholder || 'Selecciona una opción' }}
            </option>
            <option
                v-for="option in field.options"
                :key="option"
                :value="option"
            >
                {{ option }}
            </option>
        </select>
        <fieldset
            v-else-if="
                ['radio', 'multiselect', 'boolean', 'rating'].includes(
                    field.type,
                )
            "
            class="choice-group"
            :class="{
                'choice-group--multiple': field.type === 'multiselect',
            }"
        >
            <legend class="visually-hidden">{{ field.label }}</legend>
            <template v-if="field.type === 'boolean'">
                <label
                    v-for="(option, i) in field.options ?? ['Sí', 'No']"
                    :key="i"
                    class="choice"
                >
                    <input
                        v-model="model"
                        type="radio"
                        :name="id"
                        :value="i === 0"
                        :required="field.required"
                    />{{ option }}
                </label>
            </template>
            <template v-else-if="field.type === 'rating'">
                <label
                    v-for="star in [5, 4, 3, 2, 1]"
                    :key="star"
                    class="choice"
                >
                    <input
                        v-model="model"
                        type="radio"
                        :name="id"
                        :value="star"
                        :required="field.required"
                    />
                    <span class="rating-stars" aria-hidden="true">
                        <svg
                            v-for="i in star"
                            :key="i"
                            class="rating-star"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M12 3.2l2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.5l-5.2 2.7 1-5.75-4.2-4.1 5.8-.85L12 3.2z"
                            />
                        </svg>
                    </span>
                    <span class="visually-hidden">{{ star }} de 5</span>
                </label>
            </template>
            <template v-else>
                <label
                    v-for="option in field.options"
                    :key="option"
                    class="choice"
                >
                    <input
                        v-model="model"
                        :type="
                            field.type === 'multiselect' ? 'checkbox' : 'radio'
                        "
                        :name="id"
                        :value="option"
                        :required="field.type === 'radio' && field.required"
                    />{{ option }}
                </label>
            </template>
        </fieldset>
        <label v-else-if="field.type === 'checkbox'" class="choice"
            ><input :id="id" v-model="model as boolean" type="checkbox" />{{
                field.label
            }}</label
        >
        <div v-else-if="field.type === 'guests'">
            <div
                v-for="(guest, i) in model as GuestAnswer[]"
                :key="i"
                class="guest-row"
            >
                <label
                    v-for="(label, key) in {
                        name: 'Nombre completo',
                        email: 'Correo electrónico',
                        topics: 'Temas de interés',
                    }"
                    :key="key"
                >
                    <span class="visually-hidden">{{ label }}</span>
                    <input
                        :value="guest[key]"
                        :placeholder="label"
                        :type="key === 'email' ? 'email' : 'text'"
                        :required="field.required && key !== 'topics'"
                        class="form-control"
                        @input="updateGuest(i, key, $event)"
                    />
                    <span v-if="error(`.${i}.${key}`)" class="field-error">{{
                        error(`.${i}.${key}`)
                    }}</span>
                </label>
            </div>
        </div>
        <p v-if="error()" role="alert" class="field-error">{{ error() }}</p>
        <p
            v-for="(message, key) in Object.fromEntries(
                Object.entries(errors).filter(
                    ([key]) =>
                        key.startsWith(id + '.') &&
                        field.type === 'multiselect',
                ),
            )"
            :key="key"
            role="alert"
            class="field-error"
        >
            {{ message }}
        </p>
    </div>
</template>

<style scoped>
.choice-group {
    border: 0;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
}
.rating-stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}
.rating-star {
    width: 24px;
    height: 24px;
    fill: none;
    stroke: #aaaaaa;
    stroke-width: 1.5;
    stroke-linejoin: round;
}
.choice {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    min-height: 24px;
    cursor: pointer;
}
.choice input {
    accent-color: var(--accent);
}
.choice-group--multiple {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
}
.choice-group--multiple .choice {
    min-height: auto;
    white-space: nowrap;
}
.choice-group--multiple input {
    flex-shrink: 0;
}
.guest-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}
</style>
