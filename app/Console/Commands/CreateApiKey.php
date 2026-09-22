<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Client;
use Illuminate\Console\Command;

class CreateApiKey extends Command
{
    protected $signature = 'api-key:create {name : Nombre descriptivo (ej. templet_operator)} {--client= : Slug o ID del cliente al que se restringe la llave}';

    protected $description = 'Genera una API key para consumir la API de webinars';

    public function handle(): int
    {
        $clientId = null;

        if ($clientOption = $this->option('client')) {
            $client = Client::withoutGlobalScopes()
                ->where('slug', $clientOption)
                ->orWhere('id', $clientOption)
                ->first();

            if (! $client) {
                $this->error("Cliente '{$clientOption}' no encontrado.");

                return self::FAILURE;
            }

            $clientId = $client->id;
        }

        [$apiKey, $plain] = ApiKey::generate($this->argument('name'), $clientId);

        $this->info('API key creada. Guárdala ahora, no se vuelve a mostrar:');
        $this->newLine();
        $this->line("  {$plain}");
        $this->newLine();
        $this->table(
            ['ID', 'Nombre', 'Cliente'],
            [[$apiKey->id, $apiKey->name, $clientId ? $clientOption : 'todos']]
        );

        return self::SUCCESS;
    }
}
