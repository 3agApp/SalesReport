<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueOrganizationSlugs;
use App\Enums\OrganizationRole;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $timezone
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, OrganizationInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Shop> $shops
 */
#[Fillable(['name', 'slug', 'timezone'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use GeneratesUniqueOrganizationSlugs, HasFactory, SoftDeletes;

    /**
     * The statuses observed in this organization's orders, once counted.
     *
     * The count is a grouped scan of every order the organization holds, and
     * one report page asks for it three times over: for the status filter's
     * options, for what counts as revenue, and again for the page payload.
     *
     * @var array<string, int>|null
     */
    private ?array $observedOrderStatuses = null;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Set once, and never again. The slug is the organization's address:
        // every saved report link, every bookmark and every invitation that
        // has already gone out is built on it, and following a rename would
        // break all of them to keep a URL tidy. It is allowed to drift from
        // the name instead.
        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = static::generateUniqueOrganizationSlug($organization->name);
            }
        });
    }

    /**
     * Get the organization owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->first();
    }

    /**
     * The membership of the organization's owner.
     *
     * owner() answers with the user and runs a query each time. This is the
     * relation, so a listing can eager load the owner and sort or search on
     * their address without a query per row.
     *
     * @return HasOne<Membership, $this>
     */
    public function ownerMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->where('role', OrganizationRole::Owner->value);
    }

    /**
     * Get all members of this organization.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this organization.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get the timezone the organization's reports are bounded by.
     *
     * Orders are stored in UTC; this only decides where a reporting day,
     * month or quarter begins and ends.
     */
    public function reportingTimezone(): string
    {
        return $this->timezone ?? (string) config('app.reporting_timezone');
    }

    /**
     * Get the order statuses this organization has decided something about.
     *
     * @return HasMany<OrderStatusSetting, $this>
     */
    public function orderStatusSettings(): HasMany
    {
        return $this->hasMany(OrderStatusSetting::class);
    }

    /**
     * Get every order status seen in the organization's orders, with how many
     * orders are sitting in each.
     *
     * Observed rather than assumed. WooCommerce ships with seven statuses but
     * a store can register its own, and these ones do — "partial-complete",
     * "planzer-transmit", "pre-ordered". There is no list to look them up in,
     * so the orders are the list.
     *
     * @return array<string, int>
     */
    public function observedOrderStatuses(): array
    {
        if ($this->observedOrderStatuses !== null) {
            return $this->observedOrderStatuses;
        }

        /** @var array<string, int> $counts */
        $counts = Order::query()
            ->whereIn('shop_id', $this->shops()->select('id'))
            ->selectRaw('status, count(*) as order_count')
            ->groupBy('status')
            // Busiest first, then alphabetically, so the list is stable
            // rather than ordered by whatever the database returns.
            ->orderByRaw('count(*) desc')
            ->orderBy('status')
            ->pluck('order_count', 'status')
            ->all();

        return $this->observedOrderStatuses = $counts;
    }

    /**
     * Get every order status a report can be filtered by, as slug => name.
     *
     * Everything the orders have ever used, plus anything already decided on,
     * so a status keeps its name after the last order in it is archived away.
     * Being a closed list is also what keeps a status typed into a query
     * string from reaching a report.
     *
     * @return array<string, string>
     */
    public function orderStatuses(): array
    {
        $statuses = [];

        foreach (array_keys($this->observedOrderStatuses()) as $slug) {
            $statuses[$slug] = Order::statusLabel($slug);
        }

        foreach ($this->orderStatusSettings as $setting) {
            $statuses[$setting->status] = $setting->displayLabel();
        }

        return $statuses;
    }

    /**
     * Get the statuses whose orders count as money the organization has taken.
     *
     * Until somebody says otherwise, that is the two statuses where the
     * payment demonstrably went through. An unanswered status counts for
     * nothing rather than being quietly folded into revenue, so a report is
     * never inflated by a status nobody has looked at.
     *
     * @return array<string>
     */
    public function revenueStatuses(): array
    {
        $decided = $this->orderStatusSettings
            ->where('counts_as_revenue', true)
            ->pluck('status')
            ->all();

        if ($decided !== []) {
            return $decided;
        }

        // Narrowed to the statuses actually in use, so the filter does not
        // report counting a status the interface never shows.
        $fallback = array_values(array_intersect(
            Order::SETTLED_STATUSES,
            array_keys($this->observedOrderStatuses()),
        ));

        return $fallback === [] ? Order::SETTLED_STATUSES : $fallback;
    }

    /**
     * Get all shops belonging to this organization.
     *
     * @return HasMany<Shop, $this>
     */
    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    /**
     * Get the currencies the organization's shops sell in.
     *
     * One is the only workable answer. Totals in two currencies cannot be
     * added together, so a second one has to be visible on the shops page
     * rather than discovered halfway down a report.
     *
     * @return array<int, string>
     */
    public function shopCurrencies(): array
    {
        return $this->shops()
            ->whereNotNull('currency')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();
    }

    /**
     * Get all invitations for this organization.
     *
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
