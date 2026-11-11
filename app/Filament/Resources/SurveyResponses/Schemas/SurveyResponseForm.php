<?php

namespace App\Filament\Resources\SurveyResponses\Schemas;

use App\Models\SurveyResponse;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SurveyResponseForm
{
    /**
     * Los títulos de la encuesta y del webinar se copian al estado del
     * formulario: Filament lo llena con los atributos del registro, que no
     * incluyen relaciones, así que `survey.title` llegaría siempre vacío.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withRelatedTitles(array $data, SurveyResponse $record): array
    {
        $data['survey_title'] = $record->survey?->title;
        $data['webinar_title'] = $record->survey?->webinar?->title;

        return $data;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact')
                    ->schema([
                        TextInput::make('survey_title')
                            ->label('Survey')
                            ->disabled(),
                        TextInput::make('webinar_title')
                            ->label('Webinar')
                            ->placeholder('No webinar linked')
                            ->disabled(),
                        TextInput::make('name')->label('Name')->disabled(),
                        TextInput::make('email')->label('Email')->disabled(),
                        TextInput::make('phone')->label('Phone')->disabled(),
                    ])
                    ->columns(2),
                Section::make('Answers')
                    ->schema(fn (?SurveyResponse $record): array => collect($record?->answerEntries() ?? [])
                        ->map(fn (array $entry) => Placeholder::make('answer_'.str_replace('.', '_', $entry['name']))
                            ->label($entry['label'])->content($entry['value'] !== '' ? $entry['value'] : '—'))
                        ->all())
                    ->columns(2),
                Section::make('UTM Tracking')
                    ->schema([
                        TextInput::make('utm_source')->label('Source')->disabled(),
                        TextInput::make('utm_medium')->label('Medium')->disabled(),
                        TextInput::make('utm_campaign')->label('Campaign')->disabled(),
                        TextInput::make('utm_term')->label('Term')->disabled(),
                        TextInput::make('utm_content')->label('Content')->disabled(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
