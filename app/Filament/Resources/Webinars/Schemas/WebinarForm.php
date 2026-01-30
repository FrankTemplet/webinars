<?php

namespace App\Filament\Resources\Webinars\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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
                    ->helperText('Select the webinar from Zoom to enable automatic registration.')
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
                                    ->helperText('Your Facebook Pixel ID (e.g., 358413517177753)'),
                                TextInput::make('partner_id')
                                    ->label('Partner ID')
                                    ->visible(fn (Get $get) => $get('platform') === 'linkedin')
                                    ->required()
                                    ->helperText('Your LinkedIn Partner ID'),
                                TextInput::make('conversion_id')
                                    ->label('Conversion ID')
                                    ->visible(fn (Get $get) => $get('platform') === 'linkedin')
                                    ->required()
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
                                    ->visible(fn (Get $get) => $get('type') === 'select')
                                    ->helperText('Enter options for select field'),
                            ])
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

            ]);
    }
}
