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
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;

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

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('form'),
                EmptyState::make('Select a webinar')
                    ->description('Choose a client and a webinar that already took place to see its attendees.')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->visible(fn () => ! $this->selectedWebinar()),
                EmptyState::make('Not synced yet')
                    ->description(fn () => auth()->user()->isAdmin()
                        ? 'This webinar\'s attendees haven\'t been synced from Zoom yet.'
                        : 'This webinar\'s attendees haven\'t been synced from Zoom yet. Ask an admin to sync them.')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->footer([$this->syncAction()])
                    ->visible(fn () => $this->selectedWebinar()?->zoomWebinarIds() && ! $this->hasAttendees()),
                EmptyState::make('No Zoom webinar linked')
                    ->description('Link this webinar to its Zoom webinar in the webinar\'s settings to sync its attendees.')
                    ->icon(Heroicon::OutlinedLinkSlash)
                    ->visible(fn () => ($webinar = $this->selectedWebinar()) && ! $webinar->zoomWebinarIds() && ! $this->hasAttendees()),
                Section::make(fn () => $this->selectedWebinar()?->title)
                    ->description(fn () => $this->summary())
                    ->afterHeader([$this->syncAction()])
                    ->schema(fn () => $this->stats())
                    ->columns(['default' => 2, 'lg' => 4])
                    ->visible(fn () => $this->hasAttendees()),
                Section::make('By session')
                    ->schema(fn () => $this->sessionStats())
                    ->columns(['default' => 2, 'lg' => 4])
                    ->visible(fn () => $this->hasAttendees() && $this->selectedWebinar()->hasSessions()),
                EmbeddedTable::make()
                    ->visible(fn () => $this->hasAttendees()),
            ]);
    }

    /**
     * Fecha del webinar y cuándo se trajeron sus asistentes, para saber si lo
     * que se ve está al día.
     */
    private function summary(): string
    {
        $webinar = $this->selectedWebinar();

        $synced = $webinar->attendees_synced_at
            ? "Last synced {$webinar->attendees_synced_at->format('d - m - Y H:i')} UTC"
            : 'Synced before sync tracking existed — sync again to confirm these are up to date';

        return collect([$webinar->eventDateSummary(), $synced])->filter()->implode(' · ');
    }

    /**
     * @return array<Stat>
     */
    private function stats(): array
    {
        $webinar = $this->selectedWebinar();
        $sessions = $webinar->attendees()->count();
        $people = $webinar->attendees()->distinct()->count('email');
        $minutes = $people > 0 ? intdiv((int) $webinar->attendees()->sum('duration'), 60 * $people) : 0;

        return [
            Stat::make('People', number_format($people))
                ->description('Unique emails')
                ->icon(Heroicon::OutlinedUsers),
            Stat::make('External people', number_format($webinar->uniqueExternalAttendees()))
                ->description('Without internal domains')
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Zoom sessions', number_format($sessions))
                ->description($webinar->zoom_participants_count !== null
                    ? "of {$webinar->zoom_participants_count} reported by Zoom"
                    : 'Reconnections count separately')
                ->icon(Heroicon::OutlinedVideoCamera),
            Stat::make('Avg. time per person', "{$minutes} min")
                ->description('All sessions added up')
                ->icon(Heroicon::OutlinedClock),
        ];
    }

    /**
     * Personas y tiempo promedio de cada sesión, para webinars con varias.
     *
     * @return array<Stat>
     */
    private function sessionStats(): array
    {
        $webinar = $this->selectedWebinar();

        return collect($webinar->sessions)->map(function (array $session) use ($webinar) {
            $attendees = fn () => $webinar->attendees()->where('zoom_webinar_id', $session['zoom_webinar_id']);
            $people = $attendees()->distinct()->count('email');
            $minutes = $people > 0 ? intdiv((int) $attendees()->sum('duration'), 60 * $people) : 0;

            return Stat::make($session['label'], number_format($people))
                ->description($people > 0 ? "People · {$minutes} min avg." : 'No attendees synced')
                ->icon(Heroicon::OutlinedCalendarDays);
        })->all();
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
                TextColumn::make('zoom_webinar_id')
                    ->label('Session')
                    ->formatStateUsing(fn (?string $state) => $state ? $this->selectedWebinar()->sessionLabel($state) : null)
                    ->visible(fn () => (bool) $this->selectedWebinar()?->hasSessions()),
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
            ->visible(fn () => auth()->user()->isAdmin() && $this->selectedWebinar()?->zoomWebinarIds())
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
