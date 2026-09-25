<?php

namespace App\Filament\Resources\SurveyResponses\Tables;

use App\Models\Client;
use App\Models\Survey;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class SurveyResponsesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('survey.title')
                    ->label('Survey')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->placeholder('Anonymous')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('experience_rating')
                    ->label('Experience')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state) : '—'),
                TextColumn::make('stage')
                    ->label('Stage')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('wants_review')
                    ->label('Wants Review')
                    ->boolean(),
                TextColumn::make('use_case')
                    ->label('Use Case')
                    ->limit(60)
                    ->tooltip(fn ($state) => $state)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('guests')
                    ->label('Guests')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)
                    ->toggleable(),
                ...static::getExtraQuestionColumns(),
                TextColumn::make('utm_source')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('utm_medium')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('utm_campaign')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('answered')
                    ->schema([
                        Select::make('client_id')
                            ->label('Client')
                            ->options(fn () => Client::query()->pluck('name', 'id')->all())
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('survey_id', null)),
                        Select::make('survey_id')
                            ->label('Survey')
                            ->options(fn (Get $get): array => Survey::query()
                                ->where('client_id', $get('client_id'))
                                ->pluck('title', 'id')
                                ->all())
                            ->visible(fn (Get $get) => (bool) $get('client_id')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['client_id'] ?? null,
                                fn (Builder $query, $clientId) => $query->whereHas(
                                    'survey',
                                    fn (Builder $q) => $q->where('client_id', $clientId)
                                )
                            )
                            ->when(
                                $data['survey_id'] ?? null,
                                fn (Builder $query, $surveyId) => $query->where('survey_id', $surveyId)
                            );
                    })
                    ->columns(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->headerActions([
                ExportAction::make()->exports([
                    ExcelExport::make()->withColumns(static::getExcelColumns()),
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()->exports([
                        ExcelExport::make()->withColumns(static::getExcelColumns()),
                    ]),
                ]),
            ]);
    }

    /**
     * Cada invitado se aplana en tres columnas para que el Excel quede
     * con el mismo formato que producía el flujo de Power Automate.
     */
    /**
     * Preguntas adicionales de las encuestas visibles, deduplicadas por su
     * clave para que una misma pregunta no genere dos columnas.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected static function getExtraQuestions(?int $surveyId = null): \Illuminate\Support\Collection
    {
        $query = Survey::query();

        if ($surveyId) {
            $query->where('id', $surveyId);
        }

        return $query->get()
            ->flatMap(fn (Survey $survey) => $survey->extraQuestions())
            ->unique('name')
            ->values();
    }

    protected static function getExtraQuestionColumns(): array
    {
        return static::getExtraQuestions()
            ->map(fn (array $question) => TextColumn::make("extra_answers.{$question['name']}")
                ->label($question['label'])
                ->formatStateUsing(fn ($state) => match (true) {
                    is_bool($state) => $state ? 'Yes' : 'No',
                    is_array($state) => implode(', ', $state),
                    default => $state,
                })
                ->wrap()
                ->toggleable()
                ->visible(fn ($livewire) => static::shouldShowExtraColumn($question['name'], $livewire)))
            ->toArray();
    }

    /**
     * Con una encuesta filtrada solo se muestran sus propias preguntas.
     */
    protected static function shouldShowExtraColumn(string $name, $livewire): bool
    {
        $surveyId = $livewire->tableFilters['answered']['survey_id'] ?? null;

        if (! $surveyId) {
            return true;
        }

        return static::getExtraQuestions((int) $surveyId)->contains('name', $name);
    }

    protected static function getExcelColumns(): array
    {
        $columns = [
            Column::make('survey.title')->heading('Survey'),
            Column::make('name')->heading('Name'),
            Column::make('email')->heading('Email'),
            Column::make('phone')->heading('Phone'),
            Column::make('experience_rating')->heading('Experience (1-5)'),
            Column::make('use_case')->heading('Use Case / Challenges'),
            Column::make('stage')->heading('Current Stage'),
            Column::make('wants_review')
                ->heading('Wants Review')
                ->formatStateUsing(fn ($state) => $state ? 'Sí' : 'No'),
        ];

        foreach (range(0, 2) as $i) {
            $columns[] = Column::make("guests.{$i}.name")->heading('Guest '.($i + 1).' - Name');
            $columns[] = Column::make("guests.{$i}.email")->heading('Guest '.($i + 1).' - Email');
            $columns[] = Column::make("guests.{$i}.topics")->heading('Guest '.($i + 1).' - Topics');
        }

        foreach (static::getExtraQuestions() as $question) {
            $columns[] = Column::make("extra_answers.{$question['name']}")->heading($question['label']);
        }

        return array_merge($columns, [
            Column::make('utm_source')->heading('UTM Source'),
            Column::make('utm_medium')->heading('UTM Medium'),
            Column::make('utm_campaign')->heading('UTM Campaign'),
            Column::make('utm_term')->heading('UTM Term'),
            Column::make('utm_content')->heading('UTM Content'),
            Column::make('created_at')->heading('Response Date'),
        ]);
    }
}
