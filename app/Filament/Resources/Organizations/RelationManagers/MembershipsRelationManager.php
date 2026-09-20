<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Actions\Organizations\TransferOrganizationOwnership;
use App\Enums\OrganizationRole;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\UserSelect;
use App\Models\Membership;
use App\Models\Organization;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Members';

    /**
     * Members are managed from the organization's page as well as its edit page.
     */
    public function isReadOnly(): bool
    {
        return $this->organization()->trashed();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->url(fn (Membership $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
                TextColumn::make('user.email')
                    ->label('Email address'),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (OrganizationRole $state): string => $state === OrganizationRole::Owner ? 'primary' : 'gray')
                    ->formatStateUsing(fn (OrganizationRole $state): string => $state->label()),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime(),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Add member')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->schema([
                        UserSelect::make('user_id', fn (Builder $query) => $query
                            ->whereDoesntHave('organizationMemberships', fn (Builder $memberships) => $memberships->where('organization_id', $this->organization()->id)))
                            ->label('User')
                            ->required(),
                        $this->roleSelect(),
                    ])
                    ->action(function (array $data): void {
                        $this->organization()->memberships()->firstOrCreate(
                            ['user_id' => $data['user_id']],
                            ['role' => OrganizationRole::from($data['role'])],
                        );

                        Notification::make()->title('Member added')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Change role')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->hidden(fn (Membership $record): bool => $record->role === OrganizationRole::Owner)
                    ->fillForm(fn (Membership $record): array => ['role' => $record->role->value])
                    ->schema([
                        $this->roleSelect(),
                    ])
                    ->action(function (Membership $record, array $data): void {
                        $record->update(['role' => OrganizationRole::from($data['role'])]);

                        Notification::make()->title('Role updated')->success()->send();
                    }),
                Action::make('transferOwnership')
                    ->label('Make owner')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->hidden(fn (Membership $record): bool => $record->role === OrganizationRole::Owner)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Membership $record): string => "{$record->user->email} becomes the owner. The current owner stays on as an admin.")
                    ->action(function (Membership $record): void {
                        app(TransferOrganizationOwnership::class)->handle($this->organization(), $record->user);

                        Notification::make()->title('Ownership transferred')->success()->send();
                    }),
                Action::make('remove')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->hidden(fn (Membership $record): bool => $record->role === OrganizationRole::Owner)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Membership $record): string => "{$record->user->email} loses access to this organization.")
                    ->action(function (Membership $record): void {
                        $user = $record->user;

                        $record->delete();

                        if ($user->isCurrentOrganization($this->organization())) {
                            $user->switchToFallbackOrganization($this->organization());
                        }

                        Notification::make()->title('Member removed')->success()->send();
                    }),
            ]);
    }

    private function organization(): Organization
    {
        /** @var Organization */
        return $this->getOwnerRecord();
    }

    private function roleSelect(): Select
    {
        return Select::make('role')
            ->options([
                OrganizationRole::Admin->value => OrganizationRole::Admin->label(),
                OrganizationRole::Member->value => OrganizationRole::Member->label(),
            ])
            ->default(OrganizationRole::Member->value)
            ->required()
            ->native(false);
    }
}
