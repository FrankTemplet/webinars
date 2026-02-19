<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Submission;
use App\Models\Webinar;
use App\Services\ZoomService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class StatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $clientId = $this->filters['client_id'] ?? null;
        $webinarId = $this->filters['webinar_id'] ?? null;

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

        $totalSubmissions = $query
            ->where('data->email', 'not like', '%@%templet%')
            ->where('data->email', 'not like', '%@%cwc%')
            ->where('data->email', 'not like', '%@%liberynet%')
            ->distinct('data->email')
            ->count('data->email');

        // Submissions sin utm
        $submissionUtmBlanks = (clone $query)
            ->whereNull('utm_source')
            ->whereNull('utm_medium')
            ->whereNull('utm_campaign')
            ->whereNull('utm_term')
            ->whereNull('utm_content')
            ->count();

        // Asistencia al webinar (desde Zoom)
        $webinarAttendance = 0;
        if ($webinarId) {
            $webinar = Webinar::find($webinarId);
            if ($webinar && $webinar->zoom_webinar_id) {
                $zoomService = app(ZoomService::class);
                $webinarAttendance = $zoomService->getWebinarParticipants($webinar->zoom_webinar_id);
            }
        }

        // Submissions este mes
        $registeredLeads = (clone $query)
            ->where('utm_source', 'paid')
            ->count();

        // Total de clientes y webinars
        $totalClients = Client::count();
        $totalWebinars = Webinar::count();

        return [
            Stat::make('Total registers', number_format($totalSubmissions))
                ->description('Total submissions')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Register Contacts', number_format($submissionUtmBlanks))
                ->description('Blanks UTM fields')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),

            Stat::make('Webinar Attendance', number_format($webinarAttendance))
                ->description('Registered attendance')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Leads', number_format($registeredLeads))
                ->description('Leads registered')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Clients', number_format($totalClients))
                ->description('Total of clients')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('secondary'),

            Stat::make('Webinars', number_format($totalWebinars))
                ->description('Total webinars')
                ->descriptionIcon('heroicon-m-video-camera')
                ->color('danger'),
        ];
    }
}
