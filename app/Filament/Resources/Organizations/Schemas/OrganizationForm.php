<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Rules\OrganizationName;
use DateTimeZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization')
                    ->description('The URL keeps the slug it was created with; renaming does not change it.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->rule(new OrganizationName),
                        Select::make('timezone')
                            ->label('Reporting timezone')
                            ->options(collect(DateTimeZone::listIdentifiers())
                                ->mapWithKeys(fn (string $timezone) => [$timezone => str_replace('_', ' ', $timezone)])
                                ->all())
                            ->searchable()
                            ->native(false)
                            ->placeholder('Application default ('.config('app.reporting_timezone').')')
                            ->helperText('Which day an order counts towards. Leaving it empty follows the application default.'),
                    ]),
            ]);
    }
}
