<?php

namespace App\Filament\Schemas;

use App\Support\SurveyFields;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

class SurveyFieldSchema
{
    public static function make(string $name, bool $contact = false): Repeater
    {
        $types = $contact ? array_diff_key(SurveyFields::TYPES, array_flip(['rating', 'boolean', 'guests'])) : SurveyFields::TYPES;

        return Repeater::make($name)
            ->schema([
                Hidden::make('name')->default(fn () => 'field_'.Str::uuid()->toString())->required()->distinct()
                    ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                TextInput::make('label')->required()->maxLength(255),
                Select::make('type')->options($types)->required()->default('text')->live(),
                Toggle::make('required')->default(false),
                Toggle::make('hidden')->label('Hidden')->default(false)->visible($contact),
                TagsInput::make('options')
                    ->visible(fn (Get $get) => in_array($get('type'), ['select', 'radio', 'multiselect', 'boolean']))
                    ->required(fn (Get $get) => in_array($get('type'), ['select', 'radio', 'multiselect', 'boolean']))
                    ->rules([fn (Get $get) => match ($get('type')) {
                        'boolean' => 'size:2',
                        'select', 'radio', 'multiselect' => 'min:2',
                        default => 'nullable',
                    }]),
                TextInput::make('guests_count')->numeric()->integer()->minValue(1)->maxValue(10)->default(3)
                    ->required(fn (Get $get) => $get('type') === 'guests')
                    ->visible(fn (Get $get) => $get('type') === 'guests'),
            ])
            ->minItems($contact ? 0 : 1)
            ->itemLabel(fn (array $state): string => $state['label'] ?? 'New field')
            ->collapsible()->columnSpanFull();
    }
}
