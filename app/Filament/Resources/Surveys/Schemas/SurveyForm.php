<?php

namespace App\Filament\Resources\Surveys\Schemas;

use App\Models\Survey;
use App\Models\Webinar;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SurveyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('webinar_id', null)),
                Select::make('webinar_id')
                    ->label('Webinar')
                    ->helperText('The webinar this survey follows up on. Enables reporting satisfaction per webinar.')
                    ->options(fn (Get $get): array => $get('client_id')
                        ? Webinar::query()->where('client_id', $get('client_id'))->pluck('title', 'id')->all()
                        : [])
                    ->searchable()
                    ->nullable(),
                TextInput::make('title')
                    ->label('Internal Name')
                    ->helperText('Only used to find this survey in the admin, exports and filters. It is never shown on the page.')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The survey is published at /surveys/{slug}'),
                Toggle::make('is_open')
                    ->label('Open')
                    ->helperText('When disabled, the page stops accepting responses.')
                    ->live()
                    ->default(true),
                TextInput::make('closed_message')
                    ->label('Closed Message')
                    ->placeholder('Esta encuesta ya no acepta respuestas. ¡Gracias por tu interés!')
                    ->maxLength(255)
                    ->visible(fn (Get $get) => ! $get('is_open'))
                    ->columnSpanFull(),
                RichEditor::make('intro')
                    ->label('Content')
                    ->helperText('Everything shown above the questions: heading, intro paragraphs and any links.')
                    ->toolbarButtons([
                        'h2', 'h3', 'bold', 'italic', 'link',
                        'bulletList', 'orderedList', 'undo', 'redo',
                    ])
                    ->columnSpanFull(),
                FileUpload::make('hero_image')
                    ->label('Header Image')
                    ->image()
                    ->disk('public')
                    ->directory('surveys/heroes')
                    ->columnSpanFull(),
                FileUpload::make('header_logo')
                    ->label('Header Logo')
                    ->image()
                    ->disk('public')
                    ->directory('surveys/logos')
                    ->columnSpanFull(),

                Section::make('Branding')
                    ->description('The layout is shared by every survey; only these colors change per client.')
                    ->schema([
                        ColorPicker::make('accent_color')
                            ->label('Accent Color')
                            ->helperText('Buttons, focus rings and selected options.')
                            ->default('#FF6000'),
                        Select::make('accent_text_color')
                            ->label('Button Text')
                            ->helperText('Pick the one that reads against the accent color.')
                            ->options([
                                '#FFFFFF' => 'White',
                                '#1A1A1A' => 'Dark',
                            ])
                            ->default('#FFFFFF'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('Template Questions')
                    ->description('These five are always asked and cannot be removed — only their wording is editable. To ask more, use Additional Questions below.')
                    ->schema([
                        TextInput::make('q1_label')
                            ->label('1. Experience (1 to 5 star rating)')
                            ->placeholder(Survey::DEFAULT_QUESTIONS['q1_label'])
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('q2_label')
                            ->label('2. Use case or challenges (free text)')
                            ->placeholder(Survey::DEFAULT_QUESTIONS['q2_label'])
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('q3_label')
                            ->label('3. Current stage (single choice)')
                            ->placeholder(Survey::DEFAULT_QUESTIONS['q3_label'])
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TagsInput::make('q3_options')
                            ->label('Stage Options')
                            ->helperText('Leave empty to use the four default stages.')
                            ->placeholder('Add option')
                            ->columnSpanFull(),
                        TextInput::make('q4_label')
                            ->label('4. Wants a personalized review? (Yes / No)')
                            ->placeholder(Survey::DEFAULT_QUESTIONS['q4_label'])
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('yes_label')
                            ->label('4. "Yes" option')
                            ->placeholder(Survey::DEFAULT_COPY['yes_label'])
                            ->maxLength(60),
                        TextInput::make('no_label')
                            ->label('4. "No" option')
                            ->placeholder(Survey::DEFAULT_COPY['no_label'])
                            ->maxLength(60),
                        TextInput::make('q5_label')
                            ->label('5. Suggested guests')
                            ->placeholder(Survey::DEFAULT_QUESTIONS['q5_label'])
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('guests_count')
                            ->label('Guest Rows')
                            ->helperText('How many guests a respondent can suggest. 0 hides the question.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10)
                            ->default(3),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('Contact Block & Button')
                    ->description('The contact fields shown above the questions, and the submit button. Leave any text empty to use the default.')
                    ->schema([
                        Toggle::make('contact_enabled')
                            ->label('Ask for contact details')
                            ->helperText('Turn off to collect anonymous responses.')
                            ->live()
                            ->default(true),
                        TextInput::make('contact_title')
                            ->label('Heading')
                            ->placeholder(Survey::DEFAULT_COPY['contact_title'])
                            ->maxLength(255)
                            ->visible(fn (Get $get) => (bool) $get('contact_enabled')),
                        TextInput::make('contact_description')
                            ->label('Description')
                            ->placeholder(Survey::DEFAULT_COPY['contact_description'])
                            ->maxLength(255)
                            ->visible(fn (Get $get) => (bool) $get('contact_enabled'))
                            ->columnSpanFull(),
                        TextInput::make('submit_label')
                            ->label('Submit button')
                            ->placeholder(Survey::DEFAULT_COPY['submit_label'])
                            ->maxLength(60),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('Additional Questions')
                    ->description('Your own questions, asked after the template ones in this order.')
                    ->schema([
                        Repeater::make('extra_questions')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('type')
                                    ->label('Type')
                                    ->options(Survey::EXTRA_QUESTION_TYPES)
                                    ->default('text')
                                    ->required()
                                    ->live(),
                                TextInput::make('label')
                                    ->label('Question')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, $state, Get $get, Set $set) {
                                        if (blank($get('name'))) {
                                            $set('name', Str::slug($state, '_'));
                                        }
                                    })
                                    ->columnSpanFull(),
                                TextInput::make('name')
                                    ->label('Field Name')
                                    ->required()
                                    ->helperText('Key used to store the answer (e.g. recommend_score). Changing it hides previous answers.')
                                    ->rules(['regex:/^[a-z][a-z0-9_]*$/'])
                                    ->validationMessages([
                                        'regex' => 'Use lowercase letters, numbers and underscores, starting with a letter.',
                                    ]),
                                Toggle::make('required')
                                    ->label('Required')
                                    ->default(false),
                                TagsInput::make('options')
                                    ->label('Options')
                                    ->visible(fn (Get $get) => in_array($get('type'), ['select', 'radio']))
                                    ->required(fn (Get $get) => in_array($get('type'), ['select', 'radio']))
                                    ->helperText('At least two options.')
                                    ->rules([
                                        fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if (in_array($get('type'), ['select', 'radio'], true) && (! is_array($value) || count($value) < 2)) {
                                                $fail('Add at least 2 options.');
                                            }
                                        },
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->defaultItems(0)
                            ->addActionLabel('Add question')
                            ->collapsible()
                            ->collapsed()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('Thank You')
                    ->description('Shown on the same page after the response is submitted.')
                    ->schema([
                        TextInput::make('thank_you_title')
                            ->label('Title')
                            ->placeholder('¡Gracias por tu respuesta!')
                            ->maxLength(255),
                        RichEditor::make('thank_you_message')
                            ->label('Message')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->maxLength(255),
                        TextInput::make('meta_description')
                            ->label('Meta description')
                            ->maxLength(255),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
