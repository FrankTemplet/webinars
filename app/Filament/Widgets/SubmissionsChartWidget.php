<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class SubmissionsChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Registers by Employee Range';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? Carbon::now()->subDays(30)->format('Y-m-d');
        $endDate = $this->filters['endDate'] ?? Carbon::now()->format('Y-m-d');
        $clientId = $this->filters['client_id'] ?? null;
        $webinarId = $this->filters['webinar_id'] ?? null;

        $query = Submission::query()
            ->whereDate('created_at', '>=', Carbon::parse($startDate))
            ->whereDate('created_at', '<=', Carbon::parse($endDate));

        if ($webinarId) {
            $query->where('webinar_id', $webinarId);
        }
        if ($clientId) {
            $query->whereHas('webinar', fn ($q) => $q->where('client_id', $clientId));
        }

        $submissions = $query->get();

        // Agrupar por employee_range
        $employeeRangeCounts = [];
        foreach ($submissions as $submission) {
            $employeeRange = $submission->data['employee_range'] ?? 'Not specified';
            if (!isset($employeeRangeCounts[$employeeRange])) {
                $employeeRangeCounts[$employeeRange] = 0;
            }
            $employeeRangeCounts[$employeeRange]++;
        }

        // Ordenar los rangos de manera lógica
        $rangeOrder = [
            '1-10',
            '11-50',
            '51-200',
            '201-500',
            '501-1000',
            '1000+',
            'Not specified',
        ];

        $labels = [];
        $values = [];
        foreach ($rangeOrder as $range) {
            if (isset($employeeRangeCounts[$range])) {
                $labels[] = $range;
                $values[] = $employeeRangeCounts[$range];
            }
        }

        // Agregar rangos que no estén en el orden predefinido
        foreach ($employeeRangeCounts as $range => $count) {
            if (!in_array($range, $rangeOrder)) {
                $labels[] = $range;
                $values[] = $count;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Registers',
                    'data' => $values,
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(148, 163, 184, 0.8)',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
