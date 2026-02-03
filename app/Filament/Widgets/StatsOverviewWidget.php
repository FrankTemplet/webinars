<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Submission;
use App\Models\Webinar;
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

        $totalSubmissions = $query->count();

        // Submissions de hoy
        $todaySubmissions = (clone $query)
            ->whereDate('created_at', Carbon::today())
            ->count();

        // Submissions esta semana
        $weekSubmissions = (clone $query)
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();

        // Submissions este mes
        $monthSubmissions = (clone $query)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // Total de clientes y webinars
        $totalClients = Client::count();
        $totalWebinars = Webinar::count();

        return [
            Stat::make('Total registers', number_format($totalSubmissions))
                ->description('Total submissions')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Today', number_format($todaySubmissions))
                ->description('Today registers')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),

            Stat::make('This week', number_format($weekSubmissions))
                ->description('Last 7 days')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make('This month', number_format($monthSubmissions))
                ->description('Last 30 days')
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
