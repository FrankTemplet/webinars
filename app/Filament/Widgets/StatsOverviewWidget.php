<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Submission;
use App\Models\Webinar;
use App\Services\ZoomService;
use App\Support\InternalEmailDomains;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        // Retornar el número de columnas basado en cuántos stats vamos a mostrar
        $webinarId = $this->filters['webinar_id'] ?? null;

        if (! $webinarId) {
            return 1; // Una columna para el mensaje
        }

        $webinar = Webinar::find($webinarId);

        return 2;
    }

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

        // Dominios internos del cliente seleccionado (o solo los nuestros si no
        // hay filtro). Antes la lista estaba escrita a mano aquí y decía
        // 'liberynet' sin la b, así que nunca excluyó a LibertyNet.
        $client = $clientId
            ? Client::withoutGlobalScopes()->find($clientId)
            : ($webinarId ? Webinar::withoutGlobalScopes()->find($webinarId)?->client : null);

        $internalDomains = InternalEmailDomains::for($client);

        $emailExpr = "JSON_UNQUOTE(JSON_EXTRACT(data, '\$.email'))";

        $totalSubmissions = InternalEmailDomains::scopeExternal((clone $query), $emailExpr, $internalDomains)
            ->distinct()
            ->count(DB::raw("LOWER({$emailExpr})"));

        // Submissions sin utm
        $submissionUtmBlanks = (clone $query)
            ->whereNull('utm_source')
            ->whereNull('utm_medium')
            ->whereNull('utm_campaign')
            ->whereNull('utm_term')
            ->whereNull('utm_content')
            ->count();

        // Si no hay webinar seleccionado, mostrar mensaje
        if (! $webinarId) {
            return [
                Stat::make('Select a Webinar to View Statistics', '')
                    ->description('Please select a webinar from the filters above to view detailed metrics including registrations, leads, attendance, and Meta Ads insights.')
                    ->descriptionIcon('heroicon-m-funnel')
                    ->color('warning')
                    ->extraAttributes(['class' => 'col-span-full']),
            ];
        }

        // Obtener el webinar seleccionado
        $webinar = Webinar::find($webinarId);
        if (! $webinar) {
            return [
                Stat::make('Webinar not found', 'The selected webinar could not be found')
                    ->description('Please select a valid webinar')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        // Submissions con utm_source = paid
        $registeredLeads = (clone $query)
            ->where('utm_source', 'paid')
            ->count();

        // Asistencia al webinar (desde Zoom)
        $webinarAttendance = 0;
        if ($webinar->zoom_webinar_id) {
            $zoomService = app(ZoomService::class);
            $webinarAttendance = $zoomService->getWebinarParticipants($webinar->zoom_webinar_id);
        }

        // --- NEW META ADS LOGIC ---
        $totalAdSpend = $webinar->ad_spend ?? 0;

        // Calculate CPL (Cost Per Lead)
        // Formula: Ad Spend / Total Registrations
        $cpl = $totalSubmissions > 0 ? ($totalAdSpend / $totalSubmissions) : 0;

        $stats = [
            Stat::make('Total registers', number_format($totalSubmissions))
                ->description('Total submissions')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Register Contacts', number_format($submissionUtmBlanks))
                ->description('Blanks UTM fields')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),

            Stat::make('Leads', number_format($registeredLeads))
                ->description('Leads registered (paid)')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Webinar Attendance', number_format($webinarAttendance))
                ->description('Registered attendance')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Total Ad Spend', '$'.number_format($totalAdSpend, 2))
                ->description('Synced from Meta Ads')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('Cost Per Lead (CPL)', '$'.number_format($cpl, 2))
                ->description('Spend / Registrations')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($cpl < 10 ? 'success' : 'danger'),
        ];

        return $stats;
    }
}
