<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use App\Models\Webinar;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class DynamicFieldChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    public string $fieldName = '';
    public string $fieldLabel = '';
    public string $fieldType = 'text';

    protected static bool $isDiscovered = false;

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return $this->fieldLabel ?: $this->fieldName;
    }

    protected function getData(): array
    {
        $webinarId = $this->filters['webinar_id'] ?? null;
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $query = Submission::query();

        if ($webinarId) {
            $query->where('webinar_id', $webinarId);
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate));
        }

        $submissions = $query->get();

        $isSelectType = in_array($this->fieldType, ['select', 'radio']);

        $counts = [];
        foreach ($submissions as $sub) {
            $value = $sub->data[$this->fieldName] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        arsort($counts);

        if (!$isSelectType) {
            $counts = array_slice($counts, 0, 10, true);
        }

        $colors = [
            '#3b82f6', '#22c55e', '#f97316', '#8b5cf6', '#ec4899',
            '#f59e0b', '#06b6d4', '#10b981', '#ef4444', '#84cc16',
        ];

        $total = count($counts);
        $chartColors = $total <= count($colors)
            ? array_slice($colors, 0, $total)
            : array_map(fn ($i) => $colors[$i % count($colors)], range(0, $total - 1));

        return [
            'datasets' => [
                [
                    'label' => 'Registers',
                    'data' => array_values($counts),
                    'backgroundColor' => $chartColors,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_keys($counts),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        $isSelectType = in_array($this->fieldType, ['select', 'radio']);

        return [
            'indexAxis' => $isSelectType ? 'x' : 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
