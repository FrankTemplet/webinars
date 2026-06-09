<?php

namespace App\Filament\Resources\Webinars\Pages;

use App\Filament\Resources\Webinars\WebinarResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWebinar extends CreateRecord
{
    protected static string $resource = WebinarResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si no hay form_schema o está vacío, establecer campos por defecto
        if (empty($data['form_schema'])) {
            $data['form_schema'] = $this->getDefaultFormSchema();
        }

        return $data;
    }

    protected function getDefaultFormSchema(): array
    {
        return [
            [
                'type' => 'text',
                'label' => 'First Name',
                'name' => 'first_name',
                'placeholder' => 'Enter your first name',
                'required' => true,
            ],
            [
                'type' => 'text',
                'label' => 'Last Name',
                'name' => 'last_name',
                'placeholder' => 'Enter your last name',
                'required' => true,
            ],
            [
                'type' => 'email',
                'label' => 'Email',
                'name' => 'email',
                'placeholder' => 'Enter your email',
                'required' => true,
            ],
            [
                'type' => 'tel',
                'label' => 'Phone Number',
                'name' => 'phone_number',
                'placeholder' => 'Enter your phone number',
                'required' => true,
            ],
            [
                'type' => 'text',
                'label' => 'Company',
                'name' => 'company',
                'placeholder' => 'Enter your company name',
                'required' => true,
            ],
            [
                'type' => 'text',
                'label' => 'Country',
                'name' => 'country',
                'placeholder' => 'Enter your country',
                'required' => true,
            ],
            [
                'type' => 'text',
                'label' => 'Job Title',
                'name' => 'job_title',
                'placeholder' => 'Enter your job title',
                'required' => true,
            ],
            [
                'type' => 'select',
                'label' => 'Employee Range',
                'name' => 'employee_range',
                'placeholder' => 'Select employee range',
                'required' => true,
                'options' => [
                    '1-10',
                    '11-50',
                    '51-200',
                    '201-500',
                    '501-1000',
                    '1000+',
                ],
            ],
        ];
    }
}
