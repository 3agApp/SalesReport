<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Filament\Resources\Users\UserResource;
use App\Models\Organization;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('ownerMembership.user.email')
                            ->label('Owner')
                            ->placeholder('No owner')
                            ->url(fn (Organization $record): ?string => $record->ownerMembership
                                ? UserResource::getUrl('view', ['record' => $record->ownerMembership->user_id])
                                : null),
                        TextEntry::make('memberships_count')
                            ->label('Members')
                            ->state(fn (Organization $record): int => $record->memberships()->count()),
                        TextEntry::make('shops_count')
                            ->label('Shops')
                            ->state(fn (Organization $record): int => $record->shops()->count()),
                        TextEntry::make('timezone')
                            ->label('Reporting timezone')
                            // An organization that has not chosen one follows
                            // the application default, and which timezone that
                            // resolves to is the useful thing to show.
                            ->state(fn (Organization $record): string => $record->timezone
                                ?? $record->reportingTimezone().' (application default)'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('deleted_at')
                            ->label('Deleted')
                            ->dateTime()
                            ->color('danger')
                            ->visible(fn (Organization $record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
