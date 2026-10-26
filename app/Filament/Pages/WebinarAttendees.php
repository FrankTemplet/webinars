<?php

namespace App\Filament\Pages;

use App\Jobs\SyncAttendeesJob;
use App\Models\Attendee;
use App\Models\Client;
use App\Models\Webinar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Asistentes de un webinar, leídos de la base y no de Zoom, así que siguen
 * disponibles aunque Zoom ya haya expirado el webinar. Si todavía no se han
 * traído, ofrece sincronizarlos.
 */
class WebinarAttendees extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Webinars Attendees';

    protected static ?string $title = 'Webinars Attendees';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.webinar-attendees';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Client')
                    ->options(fn () => Client::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('webinar_id', null)),
                Select::make('webinar_id')
                    ->label('Webinar')
                    // Solo los de días anteriores: uno de hoy puede no haber empezado
                    // todavía y Zoom no tendría reporte que sincronizar.
                    ->options(fn (Get $get) => Webinar::query()
                        ->whereDate('event_date', '<', today())
                        ->when($get('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId))
                        ->orderByDesc('event_date')
                        ->get()
                        ->mapWithKeys(fn (Webinar $webinar) => [
                            $webinar->id => $webinar->event_date
                                ? "{$webinar->title} — {$webinar->event_date->format('d - m - Y')}"
                                : $webinar->title,
                        ]))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function selectedWebinar(): ?Webinar
    {
        $webinarId = $this->data['webinar_id'] ?? null;

        return $webinarId ? Webinar::find($webinarId) : null;
    }

    public function hasAttendees(): bool
    {
        return (bool) $this->selectedWebinar()?->attendees()->exists();
    }

    /**
     * Cuándo se trajeron por última vez y si coinciden con lo que Zoom reportó,
     * para saber si lo que se ve está al día.
     */
    public function syncSummary(): ?string
    {
        $webinar = $this->selectedWebinar();

        if (! $webinar?->attendees_synced_at) {
            return 'Synced before sync tracking existed. Sync again to confirm these are up to date.';
        }

        $saved = $webinar->attendees()->count();
        $people = $webinar->attendees()->distinct()->count('email');

        return "Last synced {$webinar->attendees_synced_at->format('d - m - Y H:i')} UTC · "
            ."{$saved} of {$webinar->zoom_participants_count} Zoom sessions saved · {$people} people";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Attendee::query()->where('webinar_id', $this->data['webinar_id'] ?? 0))
            ->defaultSort('join_time')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('join_time')
                    ->label('Joined')
                    ->dateTime('d - m - Y H:i')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Minutes')
                    ->formatStateUsing(fn (int $state) => intdiv($state, 60))
                    ->sortable(),
                TextColumn::make('device')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public function syncAction(): Action
    {
        return Action::make('sync')
            ->label('Sync from Zoom')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn () => auth()->user()->isAdmin() && filled($this->selectedWebinar()?->zoom_webinar_id))
            ->action(function () {
                $webinar = $this->selectedWebinar();
                $previousSync = $webinar->attendees_synced_at;

                SyncAttendeesJob::dispatchSync($webinar->id);

                $webinar->refresh();
                $count = $webinar->attendees()->count();

                match (true) {
                    $webinar->attendees_synced_at == $previousSync => Notification::make()->title('Zoom\'s report could not be read. Nothing was changed.')->danger()->send(),
                    $count > 0 => Notification::make()->title("Synced {$count} attendees from Zoom")->success()->send(),
                    default => Notification::make()->title('Zoom returned no attendees for this webinar')->warning()->send(),
                };
            });
    }
}
