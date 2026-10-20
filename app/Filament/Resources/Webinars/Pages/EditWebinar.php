<?php

namespace App\Filament\Resources\Webinars\Pages;

use App\Filament\Actions\FormPreviewAction;
use App\Filament\Actions\OpenLiveAction;
use App\Filament\Resources\Webinars\WebinarResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditWebinar extends EditRecord
{
    protected static string $resource = WebinarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FormPreviewAction::forWebinar(),
            OpenLiveAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
