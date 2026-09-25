<?php

namespace App\Filament\Resources\SurveyResponses\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SurveyResponseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact')
                    ->schema([
                        TextInput::make('survey.title')->label('Survey')->disabled(),
                        TextInput::make('name')->label('Name')->disabled(),
                        TextInput::make('email')->label('Email')->disabled(),
                        TextInput::make('phone')->label('Phone')->disabled(),
                    ])
                    ->columns(2),
                Section::make('Answers')
                    ->schema([
                        TextInput::make('experience_rating')
                            ->label('Experience')
                            ->formatStateUsing(fn ($state) => $state ? $state.' / 5' : '—')
                            ->disabled(),
                        TextInput::make('stage')->label('Current Stage')->disabled(),
                        Toggle::make('wants_review')->label('Wants Personalized Review')->disabled(),
                        Textarea::make('use_case')->label('Use Case / Challenges')->rows(5)->disabled()->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Additional Answers')
                    ->schema(function ($record) {
                        $questions = $record?->survey?->extraQuestions() ?? [];

                        if (empty($questions)) {
                            return [
                                Placeholder::make('no_extra')
                                    ->hiddenLabel()
                                    ->content('This survey has no additional questions.'),
                            ];
                        }

                        return collect($questions)
                            ->map(fn (array $question) => TextInput::make("extra_answers.{$question['name']}")
                                ->label($question['label'])
                                ->formatStateUsing(fn ($state) => is_bool($state) ? ($state ? 'Yes' : 'No') : $state)
                                ->disabled())
                            ->toArray();
                    })
                    ->columns(2)
                    ->collapsible(),
                Section::make('Suggested Guests')
                    ->schema([
                        Repeater::make('guests')
                            ->schema([
                                TextInput::make('name')->label('Name')->disabled(),
                                TextInput::make('email')->label('Email')->disabled(),
                                TextInput::make('topics')->label('Topics of Interest')->disabled(),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
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
