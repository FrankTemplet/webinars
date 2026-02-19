<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class SubmissionsByWebinarChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Registers by Country';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
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

        $submissions = $query->get();

        // Agrupar por país
        $countryCounts = [];
        foreach ($submissions as $submission) {
            $country = $submission->data['country'] ?? 'Not specified';
            if (!isset($countryCounts[$country])) {
                $countryCounts[$country] = 0;
            }
            $countryCounts[$country]++;
        }

        // Ordenar por cantidad descendente
        arsort($countryCounts);

        $labels = array_keys($countryCounts);
        $values = array_values($countryCounts);

        $colors = [
            '#22c55e', '#8b5cf6', '#f97316', '#06b6d4', '#f59e0b',
            '#ec4899', '#10b981', '#3b82f6', '#ef4444', '#84cc16',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Registers',
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
