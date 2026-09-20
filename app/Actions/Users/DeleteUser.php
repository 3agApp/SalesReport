<?php

namespace App\Actions\Users;

use App\Actions\Organizations\HandOverOwnedOrganizations;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    public function __construct(private HandOverOwnedOrganizations $handOverOwnedOrganizations)
    {
        //
    }

    /**
     * Delete the user, leaving no organization without an owner.
     *
     * The organizations are handed to whoever else is in them rather than
     * deleted with the user: removing one account should not take work its
     * other members are still doing.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->handOverOwnedOrganizations->handle($user);

            $user->delete();
        });
    }
}
