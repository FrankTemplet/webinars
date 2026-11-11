<?php

namespace App\Filament\Resources\Surveys\Schemas;

use App\Filament\Schemas\ThemeSection;
use App\Models\Survey;
use App\Models\Webinar;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
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
                FileUpload::make('mobile_hero_image')
                    ->label('Mobile Image')
                    ->helperText('Optional. Uses the main image when empty.')
                    ->image()->disk('public')->directory('surveys/heroes')->columnSpanFull(),
                FileUpload::make('header_logo')
                    ->label('Header Logo')
                    ->image()
                    ->disk('public')
                    ->directory('surveys/logos')
                    ->columnSpanFull(),

                ThemeSection::forPage(),

                Section::make('Questions')
                    ->schema([
                        \App\Filament\Schemas\SurveyFieldSchema::make('question_schema')
                            ->default(fn () => \App\Support\SurveyFields::legacyQuestions(new Survey(['guests_count' => 3])))
                            ->afterStateHydrated(function ($component, $state, ?Survey $record) {
                                if ($state === null && $record) {
                                    $component->state($record->questionFields());
                                }
                            }),
                    ])->columnSpanFull(),

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
                        \App\Filament\Schemas\SurveyFieldSchema::make('contact_schema', contact: true)
                            ->default(\App\Support\SurveyFields::defaultContact())
                            ->afterStateHydrated(function ($component, $state, ?Survey $record) {
                                if ($state === null && $record) {
                                    $component->state($record->contactFields());
                                }
                            }),
                        TextInput::make('submit_label')
                            ->label('Submit button')
                            ->placeholder(Survey::DEFAULT_COPY['submit_label'])
                            ->maxLength(60),
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
