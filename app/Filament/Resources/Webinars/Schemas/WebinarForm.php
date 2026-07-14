<?php

namespace App\Filament\Resources\Webinars\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class WebinarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('client_id')
                    ->relationship('client', 'name')
                    ->required(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                TextInput::make('subtitle')
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Select::make('zoom_webinar_id')
                    ->label('Zoom Webinar')
                    ->helperText("Choose the webinar from Zoom to enable automatic registration.")
                    ->options(fn () => app(\App\Services\ZoomService::class)->listWebinars())
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        if ($state) {
                            $schema = $get('form_schema') ?? [];
                            $names = array_column($schema, 'name');

                            $toAdd = [];
                            if (!in_array('email', $names)) {
                                $toAdd[] = ['type' => 'email', 'label' => 'Email', 'name' => 'email', 'required' => true];
                            }
                            if (!in_array('first_name', $names) && !in_array('nombre', $names)) {
                                $toAdd[] = ['type' => 'text', 'label' => 'Nombre', 'name' => 'first_name', 'required' => true];
                            }
                            if (!in_array('last_name', $names) && !in_array('apellido', $names)) {
                                $toAdd[] = ['type' => 'text', 'label' => 'Apellido', 'name' => 'last_name', 'required' => true];
                            }

                            if (!empty($toAdd)) {
                                $set('form_schema', array_merge($schema, $toAdd));
                            }
                        }
                    }),
                TextInput::make('clay_webhook_url')
                    ->label('Clay Webhook URL')
                    ->helperText('Enter your Clay webhook URL to automatically enrich lead data on each registration.')
                    ->url()
                    ->nullable()
                    ->columnSpanFull(),
                TextInput::make('meta_campaign_id')
                    ->label('Meta Campaign ID')
                    ->helperText('The ID of the Facebook Ads campaign for this webinar. Used for cost tracking.')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('campaign')
                    ->label('Campaign Salesforce Field')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
                FileUpload::make('hero_image')
                    ->image()
                    ->disk('public')
                    ->directory('webinars/heroes')
                    ->columnSpanFull(),
                FileUpload::make('header_logo')
                    ->image()
                    ->disk('public')
                    ->directory('webinars/logos')
                    ->columnSpanFull(),
                Section::make('Tracking Scripts')
                    ->description('Configure tracking pixels for this webinar (Facebook, LinkedIn, etc.)')
                    ->schema([
                        Repeater::make('tracking_scripts')
                            ->schema([
                                Select::make('platform')
                                    ->options([
                                        'facebook' => 'Facebook Pixel',
                                        'linkedin' => 'LinkedIn Insight Tag',
                                    ])
                                    ->required()
                                    ->live(),
                                TextInput::make('pixel_id')
                                    ->label('Pixel ID')
                                    ->visible(fn (Get $get) => $get('platform') === 'facebook')
                                    ->required()
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? preg_replace('/\D+/', '', $state) : null)
                                    ->helperText('Your Facebook Pixel ID (e.g., 358413517177753)'),
                                TextInput::make('access_token')
                                    ->label('Conversions API Access Token')
                                    ->visible(fn (Get $get) => $get('platform') === 'facebook')
                                    ->password()
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $state) : null)
                                    ->helperText('Optional. Get from Meta Events Manager → Settings → Generate Access Token. Required for server-side tracking.'),
                                TextInput::make('partner_id')
                                    ->label('Partner ID')
                                    ->visible(fn (Get $get) => $get('platform') === 'linkedin')
                                    ->required()
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? preg_replace('/\D+/', '', $state) : null)
                                    ->helperText('Your LinkedIn Partner ID'),
                                TextInput::make('conversion_id')
                                    ->label('Conversion ID')
                                    ->visible(fn (Get $get) => $get('platform') === 'linkedin')
                                    ->required()
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? preg_replace('/\D+/', '', $state) : null)
                                    ->helperText('Your LinkedIn Conversion ID (e.g., 25868049)'),
                                Toggle::make('enabled')
                                    ->label('Enable Tracking')
                                    ->default(true),
                            ])
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addActionLabel('Add Tracking Pixel')
                            ->collapsible(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
                Section::make('Thank You Page')
                    ->description('Redirect registrants to a dedicated thank you page after submitting the form.')
                    ->schema([
                        Toggle::make('thank_you_enabled')
                            ->label('Activar página de gracias')
                            ->helperText('When enabled, users are redirected to /thank-you after registering instead of seeing the inline message.')
                            ->live()
                            ->default(false),
                        TextInput::make('thank_you_title')
                            ->label('Title')
                            ->placeholder('¡Gracias por registrarte!')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => (bool) $get('thank_you_enabled')),
                        RichEditor::make('thank_you_message')
                            ->label('Message')
                            ->helperText('Shown below the title. If empty, a default message is displayed.')
                            ->visible(fn (Get $get) => (bool) $get('thank_you_enabled'))
                            ->columnSpanFull(),
                        FileUpload::make('thank_you_image')
                            ->label('Background Image')
                            ->image()
                            ->disk('public')
                            ->directory('webinars/thank-you')
                            ->required(fn (Get $get) => (bool) $get('thank_you_enabled'))
                            ->visible(fn (Get $get) => (bool) $get('thank_you_enabled'))
                            ->columnSpanFull(),
                        TextInput::make('thank_you_cta_text')
                            ->label('CTA Button Text')
                            ->placeholder('Volver')
                            ->maxLength(255)
                            ->helperText('Defaults to "Volver" if left empty.')
                            ->visible(fn (Get $get) => (bool) $get('thank_you_enabled')),
                        TextInput::make('thank_you_cta_url')
                            ->label('CTA Button URL')
                            ->url()
                            ->helperText('Defaults to the webinar page if left empty.')
                            ->visible(fn (Get $get) => (bool) $get('thank_you_enabled')),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
                Section::make('Dashboard Charts')
                    ->description('Select which form fields to display as bar charts in the dashboard when this webinar is selected.')
                    ->schema([
                        Select::make('chartable_fields')
                            ->label('Chartable Fields')
                            ->multiple()
                            ->options(function (Get $get) {
                                $schema = $get('form_schema') ?? [];
                                return collect($schema)
                                    ->filter(fn ($f) => !empty($f['name']) && !empty($f['label']))
                                    ->mapWithKeys(fn ($f) => [$f['name'] => ($f['label'] ?? $f['name']) . ' (' . ($f['type'] ?? 'text') . ')'])
                                    ->toArray();
                            })
                            ->helperText('Text fields show top 10 values. Select/Radio fields show full distribution.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
                Section::make('Form Builder')
                    ->schema([
                        Repeater::make('form_schema')
                            ->schema([
                                Select::make('type')
                                    ->options([
                                        'text' => 'Text',
                                        'email' => 'Email',
                                        'tel' => 'Phone',
                                        'number' => 'Number',
                                        'select' => 'Select',
                                        'checkbox' => 'Checkbox',
                                        'textarea' => 'Textarea',
                                        'radio' => 'Radio Button Group',
                                    ])
                                    ->required()
                                    ->live(),
                                TextInput::make('label')
                                    ->required(),
                                TextInput::make('placeholder'),
                                TextInput::make('name')
                                    ->required()
                                    ->helperText('The field name for submission data (e.g., first_name)'),
                                Toggle::make('required')
                                    ->default(true),
                                TagsInput::make('options')
                                    ->visible(fn (Get $get) => in_array($get('type'), ['select', 'radio']))
                                    ->required(fn (Get $get) => in_array($get('type'), ['select', 'radio']))
                                    ->helperText(fn (Get $get) => $get('type') === 'radio' ? 'Ingresa las opciones para el grupo (mínimo 2).' : 'Ingresa las opciones para el campo de selección.')
                                    ->rules([
                                        fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if ($get('type') === 'radio' && (!is_array($value) || count($value) < 2)) {
                                                $fail('El grupo de botones de radio debe tener al menos 2 opciones.');
                                            }
                                        },
                                    ]),
                            ])
                            ->default([
                                [
                                    'type' => 'text',
                                    'label' => 'First Name',
                                    'name' => 'first_name',
                                    'placeholder' => 'Enter your first name',
                                    'required' => true,
                                ],
                                [
                                    'type' => 'text',
                                    'label' => 'Last Name',
                                    'name' => 'last_name',
                                    'placeholder' => 'Enter your last name',
                                    'required' => true,
                                ],
                                [
                                    'type' => 'email',
                                    'label' => 'Email',
                                    'name' => 'email',
                                    'placeholder' => 'Enter your email',
                                    'required' => true,
                                ],
                                [
                                    'type' => 'tel',
                                    'label' => 'Phone Number',
                                    'name' => 'phone_number',
                                    'placeholder' => 'Enter your phone number',
                                    'required' => true,
                                ],
                                [
                                    'type' => 'text',
                                    'label' => 'Company',
                                    'name' => 'company',
                                    'placeholder' => 'Enter your company name',
                                    'required' => true,
                                ],
                                [
                                    'type' => 'text',
                                    'label' => 'Country',
                                    'name' => 'country',
                                    'placeholder' => 'Enter your country',
                                    'required' => true,
                                ],
                                [
                                    'type' => 'text',
                                    'label' => 'Job Title',
                                    'name' => 'job_title',
                                    'placeholder' => 'Enter your job title',
                                    'required' => true,
                                ],
                                [
                                    'type' => 'select',
                                    'label' => 'Employee Range',
                                    'name' => 'employee_range',
                                    'placeholder' => 'Select employee range',
                                    'required' => true,
                                    'options' => [
                                        '1-10',
                                        '11-50',
                                        '51-200',
                                        '201-500',
                                        '501-1000',
                                        '1000+',
                                    ],
                                ],
                            ])
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

            ]);
    }
}
