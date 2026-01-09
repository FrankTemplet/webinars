<?php

namespace App\Filament\Resources\Webinars;

use App\Filament\Resources\Webinars\Pages\CreateWebinar;
use App\Filament\Resources\Webinars\Pages\EditWebinar;
use App\Filament\Resources\Webinars\Pages\ListWebinars;
use App\Filament\Resources\Webinars\Schemas\WebinarForm;
use App\Filament\Resources\Webinars\Tables\WebinarsTable;
use App\Models\Webinar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebinarResource extends Resource
{
    protected static ?string $model = Webinar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Webinar';

    public static function form(Schema $schema): Schema
    {
        return WebinarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebinarsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebinars::route('/'),
            'create' => CreateWebinar::route('/create'),
            'edit' => EditWebinar::route('/{record}/edit'),
        ];
    }
}
