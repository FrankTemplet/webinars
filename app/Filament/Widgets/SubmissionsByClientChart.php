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

    protected ?string $heading = 'Registers per Channel';

    protected ?string $maxHeight = '150px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $clientId = $this->filters['client_id'] ?? null;
        $webinarId = $this->filters['webinar_id'] ?? null;

        $query = Submission::query()
            ->select('utm_medium', DB::raw('COUNT(*) as count'))
            ->whereNotNull('utm_medium')
            ->where('utm_medium', '!=', '');

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

        $data = $query
            ->groupBy('utm_medium')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        $labels = $data->pluck('utm_medium')->toArray();
        $values = $data->pluck('count')->toArray();

        // Si no hay datos, mostrar mensaje
        if (empty($labels)) {
            $labels = ['No data'];
            $values = [0];
        }

        $colors = [
            '#22c55e', '#8b5cf6', '#f97316', '#06b6d4',
            '#f59e0b', '#ec4899', '#10b981', '#3b82f6',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Registers',
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
