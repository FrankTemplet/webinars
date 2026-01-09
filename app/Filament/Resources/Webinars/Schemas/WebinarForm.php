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
                Textarea::make('tracking_scripts')
                    ->columnSpanFull(),
                TextInput::make('meta_title'),
                Textarea::make('meta_description'),
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
