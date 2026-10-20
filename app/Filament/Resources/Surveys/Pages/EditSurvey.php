<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Filament\Actions\FormPreviewAction;
use App\Filament\Actions\OpenLiveAction;
use App\Filament\Resources\Surveys\SurveyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSurvey extends EditRecord
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FormPreviewAction::forSurvey(),
            OpenLiveAction::make(),
            DeleteAction::make(),
        ];
    }
}
