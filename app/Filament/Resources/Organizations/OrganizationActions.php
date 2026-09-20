<?php

namespace App\Filament\Resources\Organizations;

use App\Actions\Organizations\DeleteOrganization;
use App\Enums\OrganizationRole;
use App\Filament\Support\UserSelect;
use App\Models\Organization;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * The actions an admin can take on a organization, shared by its pages.
 */
class OrganizationActions
{
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription('Every member is removed and pending invitations are cancelled. The organization can be restored afterwards, with a new owner.')
            ->using(function (Organization $record): bool {
                app(DeleteOrganization::class)->handle($record);

                return true;
            });
    }

    /**
     * Restore a deleted organization. Deleting removed its members, so an owner has to
     * be chosen for it to be usable again.
     */
    public static function restore(): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Organization $record): bool => $record->trashed())
            ->schema([
                UserSelect::make('owner_id')
                    ->label('Owner')
                    ->helperText('Deleting the organization removed its members, so choose who owns it now.')
                    ->required(),
            ])
            ->action(function (Organization $record, array $data): void {
                DB::transaction(function () use ($record, $data) {
                    $record->restore();

                    $record->memberships()->create([
                        'user_id' => $data['owner_id'],
                        'role' => OrganizationRole::Owner,
                    ]);
                });

                Notification::make()
                    ->title('Organization restored')
                    ->success()
                    ->send();
            });
    }
}
