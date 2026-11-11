<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Filament\Actions\FormPreviewAction;
use App\Filament\Actions\OpenLiveAction;
use App\Filament\Resources\Surveys\SurveyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSurvey extends EditRecord
{
    protected static string $resource = SurveyResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['question_schema'] ??= $this->getRecord()->questionFields();
        $data['contact_schema'] ??= $this->getRecord()->contactFields();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            FormPreviewAction::forSurvey(),
            OpenLiveAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
