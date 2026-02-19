<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\SubmissionsByClientChart;
use App\Filament\Widgets\SubmissionsByWebinarChart;
use App\Filament\Widgets\SubmissionsChartWidget;
use App\Filament\Widgets\UtmSourcesChart;
use App\Models\Client;
use App\Models\Webinar;
use App\Models\Submission;
use App\Services\ZoomService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Export to PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->action(function () {
                    return $this->exportToPdf();
                }),
        ];
    }

    protected function exportToPdf()
    {
        $filters = $this->filters;
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $webinarId = $filters['webinar_id'] ?? null;

        // Obtener nombres para los filtros
        $clientName = $clientId ? Client::find($clientId)?->name : null;
        $webinarTitle = $webinarId ? Webinar::find($webinarId)?->title : null;

        // Preparar query base
        $query = Submission::query();

        if ($startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate));
        }
        if ($webinarId) {
            $query->where('webinar_id', $webinarId);
        }
        if ($clientId) {
            $query->whereHas('webinar', fn ($q) => $q->where('client_id', $clientId));
        }

        // Calcular estadísticas
        $totalSubmissions = (clone $query)
            ->where('data->email', 'not like', '%@%templet%')
            ->where('data->email', 'not like', '%@%cwc%')
            ->where('data->email', 'not like', '%@%liberynet%')
            ->distinct('data->email')
            ->count('data->email');

        $submissionUtmBlanks = (clone $query)
            ->whereNull('utm_source')
            ->whereNull('utm_medium')
            ->whereNull('utm_campaign')
            ->whereNull('utm_term')
            ->whereNull('utm_content')
            ->count();

        $webinarAttendance = 0;
        if ($webinarId) {
            $webinar = Webinar::find($webinarId);
            if ($webinar && $webinar->zoom_webinar_id) {
                $zoomService = app(ZoomService::class);
                $webinarAttendance = $zoomService->getWebinarParticipants($webinar->zoom_webinar_id);
            }
        }

        $registeredLeads = (clone $query)
            ->where('utm_source', 'paid')
            ->count();

        $totalClients = Client::count();
        $totalWebinars = Webinar::count();

        // Datos de gráficas
        $submissions = (clone $query)->get();

        // Employee Range
        $employeeRangeCounts = [];
        foreach ($submissions as $submission) {
            $employeeRange = $submission->data['employee_range'] ?? 'Not specified';
            if (!isset($employeeRangeCounts[$employeeRange])) {
                $employeeRangeCounts[$employeeRange] = 0;
            }
            $employeeRangeCounts[$employeeRange]++;
        }

        // Country
        $countryCounts = [];
        foreach ($submissions as $submission) {
            $country = $submission->data['country'] ?? 'Not specified';
            if (!isset($countryCounts[$country])) {
                $countryCounts[$country] = 0;
            }
            $countryCounts[$country]++;
        }
        arsort($countryCounts);

        // UTM Medium
        $utmMediumCounts = (clone $query)
            ->selectRaw('utm_medium, COUNT(*) as count')
            ->whereNotNull('utm_medium')
            ->where('utm_medium', '!=', '')
            ->groupBy('utm_medium')
            ->orderByDesc('count')
            ->limit(8)
            ->pluck('count', 'utm_medium')
            ->toArray();

        // Paid vs Organic
        $paidCount = (clone $query)->where('utm_source', 'paid')->count();
        $organicCount = (clone $query)->where('utm_source', 'organic')->count();

        $data = [
            'filters' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'clientName' => $clientName,
                'webinarTitle' => $webinarTitle,
            ],
            'stats' => [
                'totalSubmissions' => $totalSubmissions,
                'submissionUtmBlanks' => $submissionUtmBlanks,
                'webinarAttendance' => $webinarAttendance,
                'registeredLeads' => $registeredLeads,
                'totalClients' => $totalClients,
                'totalWebinars' => $totalWebinars,
            ],
            'charts' => [
                'employeeRange' => $employeeRangeCounts,
                'country' => $countryCounts,
                'utmMedium' => $utmMediumCounts,
                'paidVsOrganic' => [
                    'paid' => $paidCount,
                    'organic' => $organicCount,
                ],
            ],
        ];

        $pdf = Pdf::loadView('pdf.dashboard', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'dashboard-report-' . now()->format('Y-m-d-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }
}
