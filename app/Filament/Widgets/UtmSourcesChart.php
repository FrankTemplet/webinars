<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class UtmSourcesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 5;

    protected ?string $heading = 'Paid vs Organic Traffic';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $clientId = $this->filters['client_id'] ?? null;
        $webinarId = $this->filters['webinar_id'] ?? null;

        // Query base
        $baseQuery = Submission::query();

        if ($startDate) {
            $baseQuery->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $baseQuery->whereDate('created_at', '<=', Carbon::parse($endDate));
        }
        if ($webinarId) {
            $baseQuery->where('webinar_id', $webinarId);
        }
        if ($clientId) {
            $baseQuery->whereHas('webinar', fn ($q) => $q->where('client_id', $clientId));
        }

        // Contar registros con utm_source = 'paid'
        $paidCount = (clone $baseQuery)
            ->where('utm_source', 'paid')
            ->count();

        // Contar registros con utm_source = 'organic'
        $organicCount = (clone $baseQuery)
            ->where('utm_source', 'organic')
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Registers',
                    'data' => [$paidCount, $organicCount],
                    'backgroundColor' => [
                        '#f59e0b', // Naranja para Paid
                        '#22c55e', // Verde para Organic
                    ],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => ['Paid', 'Organic'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
