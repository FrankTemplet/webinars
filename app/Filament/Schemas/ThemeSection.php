<?php

namespace App\Filament\Schemas;

use App\Support\Fonts;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * El estilo de las páginas públicas se configura en el cliente; cada webinar
 * o encuesta solo puede sobreescribir unos pocos valores.
 */
class ThemeSection
{
    private const INHERITED = 'Inherited from client';

    private const TEMPLATE = 'Template default';

    /**
     * Panel completo, en la ficha del cliente.
     */
    public static function forClient(): Section
    {
        return Section::make('Style')
            ->description('Brand for every public page of this client, both webinars and surveys. Each page can still override its accent, background and corner radius.')
            ->icon('heroicon-o-paint-brush')
            ->schema([
                Select::make('font_family')
                    ->label('Font')
                    ->helperText('Loaded from fonts.bunny.net.')
                    ->options(Fonts::options())
                    ->placeholder(self::TEMPLATE)
                    ->native(false),
                ...static::accentFields(self::TEMPLATE),
                ColorPicker::make('heading_color')
                    ->label('Headings')
                    ->placeholder(self::TEMPLATE),
                ColorPicker::make('subtitle_color')
                    ->label('Subtitle')
                    ->helperText('Only webinar landing pages use it.')
                    ->placeholder(self::TEMPLATE),
                ColorPicker::make('text_color')
                    ->label('Body Text')
                    ->placeholder(self::TEMPLATE),
                ...static::radiusFields(self::TEMPLATE),
                static::customCss('Applies to every page of this client.'),
            ])
            ->columns(2);
    }

    /**
     * Lo poco que una landing o una encuesta puede cambiar. En blanco, todo
     * hereda del cliente.
     */
    public static function forPage(): Section
    {
        return Section::make('Style')
            ->description('Only what makes this page different. The font and the text colors are set once, on the client.')
            ->icon('heroicon-o-paint-brush')
            ->collapsed()
            ->schema([
                ...static::accentFields(self::INHERITED),
                ...static::radiusFields(self::INHERITED),
                static::customCss('Added after the client CSS, so it can override it.'),
            ])
            ->columns(2)
            ->columnSpanFull()
            ->collapsible();
    }

    /**
     * @return array<int, \Filament\Forms\Components\Field>
     */
    private static function accentFields(string $placeholder): array
    {
        return [
            ColorPicker::make('accent_color')
                ->label('Accent Color')
                ->helperText('Buttons, links, focus rings and selected options.')
                ->placeholder($placeholder),
            Select::make('accent_text_color')
                ->label('Button Text')
                ->helperText('Pick the one that reads against the accent color.')
                ->options([
                    '#FFFFFF' => 'White',
                    '#1A1A1A' => 'Dark',
                ])
                ->placeholder($placeholder)
                ->native(false),
            ColorPicker::make('background_color')
                ->label('Page Background')
                ->placeholder($placeholder),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Field>
     */
    private static function radiusFields(string $placeholder): array
    {
        return [
            TextInput::make('button_radius')
                ->label('Button Radius')
                ->helperText('In pixels. 0 for square corners.')
                ->numeric()
                ->minValue(0)
                ->maxValue(64)
                ->suffix('px')
                ->placeholder($placeholder),
            TextInput::make('field_radius')
                ->label('Field Radius')
                ->helperText('In pixels. Applies to inputs, selects and textareas.')
                ->numeric()
                ->minValue(0)
                ->maxValue(64)
                ->suffix('px')
                ->placeholder($placeholder),
        ];
    }

    private static function customCss(string $helper): Textarea
    {
        return Textarea::make('custom_css')
            ->label('Custom CSS')
            ->helperText($helper.' Injected into the public page as-is.')
            ->rows(8)
            ->columnSpanFull();
    }
}
