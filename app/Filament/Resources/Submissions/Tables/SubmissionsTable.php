<?php

namespace App\Filament\Resources\Submissions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                            ->fromTable()
                            ->withColumns(static::getExcelColumns())
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                                ->fromTable()
                                ->withColumns(static::getExcelColumns())
                        ]),
                ]),
            ]);
    }

    protected static function getDynamicFields(): \Illuminate\Support\Collection
    {
        return \App\Models\Webinar::all()
            ->pluck('form_schema')
            ->filter()
            ->flatten(1)
            ->unique('name')
            ->values();
    }

    protected static function getExcelColumns(): array
    {
        $columns = [
            \pxlrbt\FilamentExcel\Columns\Column::make('webinar.title')->heading('Webinar'),
        ];

        foreach (static::getDynamicFields() as $field) {
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
        return static::getDynamicFields()->map(function ($field) {
            return TextColumn::make("data.{$field['name']}")
                ->label($field['label'] ?? \Illuminate\Support\Str::headline($field['name']))
                ->toggleable()
                ->searchable();
        })->toArray();
    }
}
