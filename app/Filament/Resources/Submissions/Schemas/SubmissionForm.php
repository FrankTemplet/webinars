<?php

namespace App\Filament\Resources\Submissions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Webinar')
                    ->schema([
                        Select::make('webinar_id')
                            ->relationship('webinar', 'title')
                            ->label('Webinar')
                            ->disabled(),
                    ]),
                Section::make('Datos de la Suscripción')
                    ->schema(function ($record) {
                        if (!$record || !$record->webinar || !$record->webinar->form_schema) {
                            return [
                                TextInput::make('data')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state)
                                    ->label('Datos (JSON)')
                                    ->disabled(),
                            ];
                        }

                        return collect($record->webinar->form_schema)->map(function ($field) {
                            $type = $field['type'] ?? 'text';
                            $name = $field['name'];
                            $label = $field['label'] ?? \Illuminate\Support\Str::headline($name);
                            $placeholder = $field['placeholder'] ?? null;
                            $required = $field['required'] ?? false;

                            $component = match ($type) {
                                'email' => TextInput::make("data.{$name}")->email(),
                                'tel' => TextInput::make("data.{$name}")->tel(),
                                'number' => TextInput::make("data.{$name}")->numeric(),
                                'textarea' => Textarea::make("data.{$name}"),
                                'select' => Select::make("data.{$name}")
                                    ->options(array_combine($field['options'] ?? [], $field['options'] ?? [])),
                                'checkbox' => Toggle::make("data.{$name}"),
                                default => TextInput::make("data.{$name}"),
                            };

                            $component
                                ->label($label)
                                ->required($required);

                            if (method_exists($component, 'placeholder') && $placeholder) {
                                $component->placeholder($placeholder);
                            }

                            return $component;
                        })->toArray();
                    }),
                Section::make('UTM Tracking')
                    ->schema([
                        TextInput::make('utm_source')->label('Source')->disabled(),
                        TextInput::make('utm_medium')->label('Medium')->disabled(),
                        TextInput::make('utm_campaign')->label('Campaign')->disabled(),
                        TextInput::make('utm_term')->label('Term')->disabled(),
                        TextInput::make('utm_content')->label('Content')->disabled(),
                    ])->columns(2)->collapsed(),
            ]);
    }
}
