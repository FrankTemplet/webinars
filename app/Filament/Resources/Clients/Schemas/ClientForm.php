<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->directory('clients/logos'),
                Repeater::make('socialMedia')
                    ->relationship('socialMedia')
                    ->schema([
                        Select::make('type')
                            ->options([
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'linkedin' => 'LinkedIn',
                                'twitter' => 'Twitter/X',
                                'youtube' => 'YouTube',
                                'tiktok' => 'TikTok',
                                'website' => 'Website',
                            ])
                            ->required(),
                        TextInput::make('url')
                            ->url()
                            ->required(),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['type'] ?? null),
            ]);
    }
}
