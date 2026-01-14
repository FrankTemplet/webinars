<?php

namespace App\Filament\Resources\Submissions\Tables;

use App\Models\Webinar;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class SubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(array_merge(
                [
                    TextColumn::make('webinar.title')
                        ->label('Webinar')
                        ->sortable()
                        ->searchable(),
                ],
                static::getDynamicColumns(),
                [
                    TextColumn::make('utm_source')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('utm_medium')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('utm_campaign')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('utm_term')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('utm_content')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]
            ))
            ->filters([
                Filter::make('submitted')
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->options(\App\Models\Client::all()->pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('webinar_id', null)),
                        Select::make('webinar_id')
                            ->label('Webinar')
                            ->options(fn (Get $get): array => Webinar::query()->where('client_id', $get('client_id'))->pluck('title', 'id')->all())
                            ->visible(fn (Get $get) => $get('client_id'))
                    ])->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['client_id'] && $data['webinar_id'],
                                fn (Builder $query) => $query->where('webinar_id', $data['webinar_id']),
                                fn (Builder $query) => $query->whereRaw('1 = 0')
                            );
                    })->columns(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                            ->withColumns(fn ($livewire) => static::getExcelColumns($livewire))
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                                ->withColumns(fn ($livewire) => static::getExcelColumns($livewire))
                        ]),
                ]),
            ]);
    }

    protected static function getDynamicFields(?int $webinarId = null): \Illuminate\Support\Collection
    {
        $query = \App\Models\Webinar::query();

        if ($webinarId) {
            $query->where('id', $webinarId);
        }

        return $query->get()
            ->pluck('form_schema')
            ->filter()
            ->flatten(1)
            ->unique('name')
            ->values();
    }

    protected static function getExcelColumns($livewire): array
    {
        $filters = $livewire->tableFilters ?? [];
        $webinarId = $filters['submitted']['webinar_id'] ?? null;

        $columns = [
            \pxlrbt\FilamentExcel\Columns\Column::make('webinar.title')->heading('Webinar'),
        ];

        foreach (static::getDynamicFields($webinarId) as $field) {
            $columns[] = \pxlrbt\FilamentExcel\Columns\Column::make("data.{$field['name']}")
                ->heading($field['label'] ?? \Illuminate\Support\Str::headline($field['name']));
        }

        return array_merge($columns, [
            \pxlrbt\FilamentExcel\Columns\Column::make('utm_source')->heading('UTM Source'),
            \pxlrbt\FilamentExcel\Columns\Column::make('utm_medium')->heading('UTM Medium'),
            \pxlrbt\FilamentExcel\Columns\Column::make('utm_campaign')->heading('UTM Campaign'),
            \pxlrbt\FilamentExcel\Columns\Column::make('utm_term')->heading('UTM Term'),
            \pxlrbt\FilamentExcel\Columns\Column::make('utm_content')->heading('UTM Content'),
            \pxlrbt\FilamentExcel\Columns\Column::make('created_at')->heading('Fecha de Creación'),
        ]);
    }

    protected static function getDynamicColumns(): array
    {
        // Obtener todos los campos únicos de todos los webinars
        return static::getDynamicFields(null)->map(function ($field) {
            return TextColumn::make("data.{$field['name']}")
                ->label($field['label'] ?? \Illuminate\Support\Str::headline($field['name']))
                ->toggleable()
                ->searchable()
                ->visible(fn ($livewire) => static::shouldShowColumn($field['name'], $livewire));
        })->toArray();
    }

    protected static function shouldShowColumn(string $fieldName, $livewire): bool
    {
        $filters = $livewire->tableFilters ?? [];
        $webinarId = $filters['submitted']['webinar_id'] ?? null;

        // Si no hay webinar seleccionado, mostrar todas las columnas
        if (!$webinarId) {
            return true;
        }

        // Obtener el webinar seleccionado
        $webinar = Webinar::find($webinarId);

        if (!$webinar || !$webinar->form_schema) {
            return false;
        }

        // Verificar si este campo está en el schema del webinar
        $fieldNames = collect($webinar->form_schema)->pluck('name')->toArray();

        return in_array($fieldName, $fieldNames);
    }
}
