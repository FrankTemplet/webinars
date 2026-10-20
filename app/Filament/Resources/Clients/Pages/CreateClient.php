<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Actions\FormPreviewAction;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FormPreviewAction::forClient(),
        ];
    }
}
