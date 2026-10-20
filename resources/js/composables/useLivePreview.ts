import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Preview en vivo de las páginas públicas dentro del panel.
 *
 * El formulario de Filament manda sus campos crudos por postMessage y aquí
 * se resuelve la misma cascada que hace el servidor: valor de la página,
 * valor heredado del cliente y, de último, el de la plantilla. El servidor
 * manda ya resuelto lo heredado (`theme.editor.inherited`), así que esta
 * parte no duplica ningún valor por defecto.
 */

export interface ThemeEditor {
    inherited: Record<string, string>;
    inherited_font?: string | null;
    inherited_css?: string | null;
    fonts: Record<string, string>;
    font_base_url: string;
}

export interface Theme {
    vars: Record<string, string>;
    font_url?: string | null;
    custom_css?: string | null;
    editor?: ThemeEditor;
}

export type Draft = Record<string, unknown>;

const COLOR_TOKENS: Record<string, string> = {
    '--accent': 'accent_color',
    '--accent-text': 'accent_text_color',
    '--heading': 'heading_color',
    '--subtitle': 'subtitle_color',
    '--text': 'text_color',
    '--bg': 'background_color',
};

const SIZE_TOKENS: Record<string, string> = {
    '--radius-button': 'button_radius',
    '--radius-field': 'field_radius',
};

const MESSAGE_IN = 'templet:preview:state';
const MESSAGE_READY = 'templet:preview:ready';

const filled = (value: unknown): value is string =>
    typeof value === 'string' && value.trim() !== '';

/**
 * Los repeaters de Filament guardan sus filas en un objeto con claves
 * generadas, no en un arreglo. La página espera una lista.
 */
export const toList = <T>(value: unknown): T[] => {
    if (Array.isArray(value)) return value as T[];
    if (value && typeof value === 'object') return Object.values(value) as T[];

    return [];
};

export function useLivePreview(getTheme: () => Theme, enabled: boolean) {
    const draft = ref<Draft | null>(null);

    /** Valor del formulario si trae algo; si no, lo que ya mostraba la página. */
    const pick = <T>(key: string, fallback: T): T => {
        if (!draft.value || !(key in draft.value)) {
            return fallback;
        }

        const value = draft.value[key];

        return filled(value) || typeof value === 'number' ? (value as T) : fallback;
    };

    const vars = computed<Record<string, string>>(() => {
        const theme = getTheme();

        if (!draft.value || !theme.editor) {
            return theme.vars;
        }

        const resolved: Record<string, string> = { ...theme.editor.inherited };

        for (const [token, column] of Object.entries(COLOR_TOKENS)) {
            if (!(token in resolved)) continue;

            const value = draft.value[column];
            if (filled(value)) resolved[token] = value.trim();
        }

        for (const [token, column] of Object.entries(SIZE_TOKENS)) {
            if (!(token in resolved)) continue;

            const value = draft.value[column];
            if (value === null || value === undefined || value === '') continue;

            resolved[token] = `${Math.max(0, parseInt(String(value), 10) || 0)}px`;
        }

        const font = (draft.value.font_family as string) || theme.editor.inherited_font;
        if (filled(font) && theme.editor.fonts[font]) {
            resolved['--font'] = `'${font}', sans-serif`;
        }

        return resolved;
    });

    const fontUrl = computed<string | null>(() => {
        const theme = getTheme();

        if (!draft.value || !theme.editor) {
            return theme.font_url ?? null;
        }

        const font = (draft.value.font_family as string) || theme.editor.inherited_font;

        return filled(font) && theme.editor.fonts[font]
            ? theme.editor.font_base_url + theme.editor.fonts[font]
            : theme.font_url ?? null;
    });

    const customCss = computed<string>(() => {
        const theme = getTheme();

        if (!draft.value || !theme.editor) {
            return theme.custom_css ?? '';
        }

        // El CSS del cliente va primero, igual que lo arma el servidor.
        return [theme.editor.inherited_css, draft.value.custom_css]
            .filter(filled)
            .join('\n\n');
    });

    let styleTag: HTMLStyleElement | null = null;

    const onMessage = (event: MessageEvent) => {
        if (event.origin !== window.location.origin) return;
        if (event.data?.type !== MESSAGE_IN) return;

        draft.value = event.data.state ?? null;
    };

    onMounted(() => {
        if (!enabled) return;

        styleTag = document.createElement('style');
        styleTag.dataset.livePreview = 'true';
        document.head.appendChild(styleTag);

        window.addEventListener('message', onMessage);

        // El panel espera este aviso para mandar el primer estado: el iframe
        // puede tardar más que el formulario en montarse.
        window.parent?.postMessage({ type: MESSAGE_READY }, window.location.origin);
    });

    onBeforeUnmount(() => {
        if (!enabled) return;

        window.removeEventListener('message', onMessage);
        styleTag?.remove();
    });

    watch(customCss, (css) => {
        if (styleTag) styleTag.textContent = css;
    });

    return { draft, pick, vars, fontUrl, customCss };
}
