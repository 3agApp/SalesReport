<?php

namespace App\Models;

use App\Concerns\HasOrganizations;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $sso_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property int|null $current_organization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $currentOrganization
 * @property-read Collection<int, Organization> $ownedOrganizations
 * @property-read Collection<int, Membership> $organizationMemberships
 * @property-read Collection<int, Organization> $organizations
 */
#[Fillable(['name', 'email', 'password', 'current_organization_id', 'sso_id'])]
// two_factor_* are the columns Fortify left behind. Nothing reads them any
// more, but they outlive the feature in the database, and every page shares
// this model as the 'auth.user' prop -- so they stay hidden until dropped.
#[Hidden(['password', 'remember_token', 'sso_id', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOrganizations, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
