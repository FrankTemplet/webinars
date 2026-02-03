<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\SubmissionsByClientChart;
use App\Filament\Widgets\SubmissionsByWebinarChart;
use App\Filament\Widgets\SubmissionsChartWidget;
use App\Filament\Widgets\UtmSourcesChart;
use App\Models\Client;
use App\Models\Webinar;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->default(now()->subDays(30)),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->default(now()),
                        Select::make('client_id')
                            ->label('Client')
                            ->options(Client::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('All clients')
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('webinar_id', null)),
                        Select::make('webinar_id')
                            ->label('Webinar')
                            ->options(function (callable $get) {
                                $clientId = $get('client_id');
                                if ($clientId) {
                                    return Webinar::where('client_id', $clientId)->pluck('title', 'id');
                                }
                                return Webinar::pluck('title', 'id');
                            })
                            ->searchable()
                            ->placeholder('All webinars'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            SubmissionsChartWidget::class,
            SubmissionsByWebinarChart::class,
            SubmissionsByClientChart::class,
            UtmSourcesChart::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
