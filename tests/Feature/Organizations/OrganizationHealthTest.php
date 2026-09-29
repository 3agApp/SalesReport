<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Organizations\OrganizationInvitation as OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->owner, ['role' => OrganizationRole::Owner->value]);
});

test('an organization without shops is asked to connect one', function () {
    $this->actingAs($this->owner)
        ->get(route('organizations.edit', $this->organization))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attention', 1)
            ->where('attention.0.key', 'no-shops')
            ->where('attention.0.target', 'shops'));
});

test('a healthy organization has nothing that needs attention', function () {
    Shop::factory()->for($this->organization)->connected()->create();

    $this->actingAs($this->owner)
        ->get(route('organizations.edit', $this->organization))
        ->assertInertia(fn (Assert $page) => $page->where('attention', []));
});

test('shops with connection problems need attention', function () {
    Shop::factory()->for($this->organization)->count(2)->failing()->create();
    Shop::factory()->for($this->organization)->connected()->create();

    $this->actingAs($this->owner)
        ->get(route('organizations.edit', $this->organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attention.0.key', 'shops-needing-attention')
            ->where('attention.0.title', '2 shops have connection problems'));
});

test('an invitation can be resent with a fresh expiry', function () {
    Notification::fake();

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $this->organization->id,
        'invited_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->post(route('organizations.invitations.resend', [$this->organization, $invitation]))
        ->assertRedirect(route('organizations.edit', $this->organization));

    expect($invitation->fresh()->isExpired())->toBeFalse();

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

test('a member cannot resend an invitation', function () {
    Notification::fake();

    $member = User::factory()->create();
    $this->organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $this->organization->id,
        'invited_by' => $this->owner->id,
    ]);

    $this->actingAs($member)
        ->post(route('organizations.invitations.resend', [$this->organization, $invitation]))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('an invitation from another organization cannot be resent through this one', function () {
    $invitation = OrganizationInvitation::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('organizations.invitations.resend', [$this->organization, $invitation]))
        ->assertNotFound();
});

test('expired invitations need attention and are marked expired', function () {
    OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $this->organization->id,
        'invited_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('organizations.edit', $this->organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attention', fn ($items) => collect($items)->pluck('key')->contains('expired-invitations'))
            ->where('invitations.0.is_expired', true));
});

test('the old appearance address leads to the profile page', function () {
    $this->actingAs($this->owner)
        ->get(route('appearance.edit'))
        ->assertRedirect('/settings/profile#appearance');
});
