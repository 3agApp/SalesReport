<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\OrganizationRole;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Membership;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lists the user's organizations. Memberships are changed from the organization's page.
 */
class OrganizationMembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'organizationMemberships';

    protected static ?string $title = 'Organizations';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('organization'))
            ->columns([
                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->url(fn (Membership $record): string => OrganizationResource::getUrl('view', ['record' => $record->organization])),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (OrganizationRole $state): string => $state->label()),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime(),
            ]);
    }
}
