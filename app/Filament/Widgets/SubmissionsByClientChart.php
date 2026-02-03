<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class SubmissionsByClientChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Registers per client';

    protected ?string $maxHeight = '150px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $query = Submission::query()
            ->join('webinars', 'submissions.webinar_id', '=', 'webinars.id')
            ->join('clients', 'webinars.client_id', '=', 'clients.id')
            ->select('clients.id', 'clients.name', DB::raw('COUNT(submissions.id) as count'));

        if ($startDate) {
            $query->whereDate('submissions.created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->whereDate('submissions.created_at', '<=', Carbon::parse($endDate));
        }

        $data = $query
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        $labels = $data->pluck('name')->toArray();
        $values = $data->pluck('count')->toArray();

        $colors = [
            '#22c55e', '#8b5cf6', '#f97316', '#06b6d4',
            '#f59e0b', '#ec4899', '#10b981', '#3b82f6',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Registros',
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                ],
            ],
        ];
    }
}
