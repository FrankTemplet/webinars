<?php

namespace App\Models;

use App\Models\Scopes\ClientAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    /**
     * Enunciados por defecto de la plantilla de satisfacción.
     * Se usan cuando la encuesta no sobreescribe el texto.
     */
    public const DEFAULT_QUESTIONS = [
        'q1_label' => '¿Cómo fue tu experiencia durante la sesión?',
        'q2_label' => 'Cuéntanos sobre tu caso de uso o los principales desafíos que estás enfrentando actualmente.',
        'q3_label' => '¿En qué etapa se encuentran actualmente frente a este caso de uso o desafío?',
        'q4_label' => '¿Te gustaría que revisemos tu caso y te compartamos recomendaciones personalizadas?',
        'q5_label' => '¿Hay alguien más de tu equipo u organización a quien quisieras invitar a esta sesión o que pueda estar interesado en conocer más sobre este tema?',
    ];

    /**
     * Copia de interfaz por defecto. Cada encuesta puede sobreescribirla.
     */
    public const DEFAULT_COPY = [
        'contact_title' => 'Déjanos tus datos',
        'contact_description' => 'Los usamos únicamente para dar seguimiento a tus respuestas.',
        'submit_label' => 'Enviar',
        'yes_label' => 'Si',
        'no_label' => 'No',
    ];

    public const DEFAULT_STAGES = [
        'Exploración inicial',
        'Evaluación de soluciones',
        'Implementación en curso',
        'Optimización y mejora continua',
    ];

    protected $fillable = [
        'client_id', 'webinar_id', 'title', 'slug', 'intro',
        'hero_image', 'header_logo', 'accent_color', 'accent_text_color', 'meta_title', 'meta_description',
        'q1_label', 'q2_label', 'q3_label', 'q3_options', 'q4_label', 'yes_label', 'no_label', 'q5_label',
        'guests_count', 'contact_enabled', 'contact_title', 'contact_description', 'submit_label',
        'extra_questions', 'is_open', 'closed_message',
        'thank_you_title', 'thank_you_message',
    ];

    protected $casts = [
        'q3_options' => 'array',
        'extra_questions' => 'array',
        'guests_count' => 'integer',
        'contact_enabled' => 'boolean',
        'is_open' => 'boolean',
    ];

    /**
     * Tipos disponibles para las preguntas que se agregan además de las cinco
     * de la plantilla.
     */
    public const EXTRA_QUESTION_TYPES = [
        'text' => 'Short text',
        'textarea' => 'Long text',
        'number' => 'Number',
        'select' => 'Dropdown',
        'radio' => 'Single choice',
        'checkbox' => 'Checkbox',
        'rating' => 'Star rating (1-5)',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope('client_id'));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * Enunciado de una pregunta, con el texto por defecto como respaldo.
     */
    public function question(string $key): string
    {
        return $this->{$key} ?: (self::DEFAULT_QUESTIONS[$key] ?? '');
    }

    /**
     * Opciones de la pregunta de etapa, con las de la plantilla como respaldo.
     *
     * @return array<int, string>
     */
    public function stageOptions(): array
    {
        return ! empty($this->q3_options) ? $this->q3_options : self::DEFAULT_STAGES;
    }

    /**
     * Texto de interfaz, con el valor por defecto como respaldo.
     */
    public function copy(string $key): string
    {
        return filled($this->{$key}) ? $this->{$key} : (self::DEFAULT_COPY[$key] ?? '');
    }

    /**
     * Preguntas adicionales normalizadas, descartando las que quedaron sin
     * nombre o sin enunciado en el repeater del admin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extraQuestions(): array
    {
        return collect($this->extra_questions ?? [])
            ->filter(fn ($q) => ! empty($q['name']) && ! empty($q['label']))
            ->map(fn ($q) => [
                'type' => $q['type'] ?? 'text',
                'name' => $q['name'],
                'label' => $q['label'],
                'required' => (bool) ($q['required'] ?? false),
                'options' => array_values($q['options'] ?? []),
            ])
            ->values()
            ->all();
    }

    /**
     * Estructura que consume la página pública de la encuesta.
     */
    public function toPublicArray(): array
    {
        $guestsCount = max(0, min(10, (int) $this->guests_count));
        $fixedCount = $guestsCount > 0 ? 5 : 4;

        return [
            'slug' => $this->slug,
            // `title` es el nombre interno del panel: no se expone a la página.
            'intro' => $this->intro,
            'hero_image' => $this->hero_image,
            'header_logo' => $this->header_logo,
            'accent_color' => $this->accent_color ?: '#FF6000',
            'accent_text_color' => $this->accent_text_color ?: '#FFFFFF',
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'is_open' => $this->is_open,
            'closed_message' => $this->closed_message,
            'thank_you_title' => $this->thank_you_title,
            'thank_you_message' => $this->thank_you_message,
            'guests_count' => $guestsCount,
            'contact_enabled' => $this->contact_enabled,
            'copy' => [
                'contact_title' => $this->copy('contact_title'),
                'contact_description' => $this->copy('contact_description'),
                'submit_label' => $this->copy('submit_label'),
                'yes_label' => $this->copy('yes_label'),
                'no_label' => $this->copy('no_label'),
            ],
            'questions' => [
                'q1' => $this->question('q1_label'),
                'q2' => $this->question('q2_label'),
                'q3' => $this->question('q3_label'),
                'q4' => $this->question('q4_label'),
                'q5' => $this->question('q5_label'),
            ],
            'stage_options' => $this->stageOptions(),
            // La numeración se calcula aquí para que ocultar la pregunta de
            // invitados no deje un hueco en la lista.
            'extra_questions' => collect($this->extraQuestions())
                ->map(fn ($q, $i) => $q + ['number' => $fixedCount + $i + 1])
                ->all(),
        ];
    }
}
