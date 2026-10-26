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
                    ->options(fn (Get $get) => Webinar::query()
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

                SyncAttendeesJob::dispatchSync($webinar->id);

                $count = $webinar->attendees()->count();

                $count > 0
                    ? Notification::make()->title("Synced {$count} attendees from Zoom")->success()->send()
                    : Notification::make()->title('Zoom returned no attendees for this webinar')->warning()->send();
            });
    }
}
