<x-filament-panels::page>
    {{ $this->form }}

    @php($webinar = $this->selectedWebinar())

    @if (! $webinar)
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Select a client and a webinar to see its attendees.</p>
        </x-filament::section>
    @elseif ($this->hasAttendees())
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                @if ($webinar->eventDateSummary())
                    <p>{{ $webinar->eventDateSummary() }}</p>
                @endif
                <p>{{ $this->syncSummary() }}</p>
            </div>

            {{ $this->syncAction }}
        </div>

        {{ $this->table }}
    @elseif (filled($webinar->zoom_webinar_id))
        <x-filament::section>
            <div class="flex flex-col items-start gap-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    This webinar's attendees haven't been synced from Zoom yet.
                </p>

                {{ $this->syncAction }}
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                This webinar has no Zoom webinar linked. Link it in the webinar's settings to sync its attendees.
            </p>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
