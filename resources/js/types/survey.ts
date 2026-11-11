export interface GuestAnswer {
    name: string;
    email: string;
    topics: string;
}
export type SurveyAnswer =
    | string
    | number
    | boolean
    | string[]
    | GuestAnswer[]
    | null;
export interface SurveyField {
    name: string;
    label: string;
    type:
        | 'text'
        | 'email'
        | 'tel'
        | 'textarea'
        | 'number'
        | 'select'
        | 'radio'
        | 'multiselect'
        | 'checkbox'
        | 'rating'
        | 'boolean'
        | 'guests';
    required?: boolean;
    hidden?: boolean;
    options?: string[];
    guests_count?: number;
}
export function initialAnswer(field: SurveyField): SurveyAnswer {
    if (field.type === 'guests')
        return Array.from({ length: field.guests_count ?? 3 }, () => ({
            name: '',
            email: '',
            topics: '',
        }));
    if (field.type === 'multiselect') return [];
    if (field.type === 'checkbox') return false;
    if (field.type === 'rating') return 5;
    if (field.type === 'boolean') return true;
    return '';
}
