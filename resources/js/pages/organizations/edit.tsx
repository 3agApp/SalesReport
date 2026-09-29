import { Form, Head, router, usePage } from '@inertiajs/react';
import { ChevronDown, Mail, Send, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import AttentionList from '@/components/attention-list';
import CancelInvitationModal from '@/components/cancel-invitation-modal';
import ChangeMemberRoleModal from '@/components/change-member-role-modal';
import DangerZone from '@/components/danger-zone';
import DeleteOrganizationModal from '@/components/delete-organization-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import InviteMemberModal from '@/components/invite-member-modal';
import RemoveMemberModal from '@/components/remove-member-modal';
import SaveButton from '@/components/save-button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { edit, index, update } from '@/routes/organizations';
import { index as shopsIndex } from '@/routes/shops';
import { resend as resendInvitation } from '@/routes/organizations/invitations';
import type {
    RoleOption,
    Organization,
    OrganizationAttentionItem,
    OrganizationInvitation,
    OrganizationMember,
    OrganizationPermissions,
} from '@/types';

type Props = {
    organization: Organization;
    members: OrganizationMember[];
    invitations: OrganizationInvitation[];
    attention: OrganizationAttentionItem[];
    permissions: OrganizationPermissions;
    availableRoles: RoleOption[];
    timezones: { value: string; label: string }[];
};

export default function OrganizationEdit({
    organization,
    members,
    invitations,
    attention,
    permissions,
    availableRoles,
    timezones,
}: Props) {
    const getInitials = useInitials();
    const { auth } = usePage().props;

    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [removeMemberDialogOpen, setRemoveMemberDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] =
        useState<OrganizationMember | null>(null);
    const [roleChange, setRoleChange] = useState<{
        member: OrganizationMember;
        role: RoleOption;
    } | null>(null);
    const [roleDialogOpen, setRoleDialogOpen] = useState(false);
    const [cancelInvitationDialogOpen, setCancelInvitationDialogOpen] =
        useState(false);
    const [invitationToCancel, setInvitationToCancel] =
        useState<OrganizationInvitation | null>(null);
    const [resending, setResending] = useState<string | null>(null);

    const pageTitle = useMemo(
        () =>
            permissions.canUpdateOrganization
                ? `Edit ${organization.name}`
                : `View ${organization.name}`,
        [permissions.canUpdateOrganization, organization.name],
    );

    const attentionItems = attention.map((item) => ({
        ...item,
        href:
            item.target === 'invitations'
                ? '#invitations'
                : shopsIndex(organization.slug).url,
    }));

    const currentMember = members.find((member) => member.id === auth.user.id);

    const confirmRoleChange = (member: OrganizationMember, value: string) => {
        const role = availableRoles.find((option) => option.value === value);

        if (!role || role.value === member.role) {
            return;
        }

        setRoleChange({ member, role });
        setRoleDialogOpen(true);
    };

    const confirmRemoveMember = (member: OrganizationMember) => {
        setMemberToRemove(member);
        setRemoveMemberDialogOpen(true);
    };

    const confirmCancelInvitation = (invitation: OrganizationInvitation) => {
        setInvitationToCancel(invitation);
        setCancelInvitationDialogOpen(true);
    };

    const resend = (invitation: OrganizationInvitation) => {
        router.visit(resendInvitation([organization.slug, invitation.code]), {
            preserveScroll: true,
            onStart: () => setResending(invitation.code),
            onFinish: () => setResending(null),
        });
    };

    return (
        <>
            <Head title={pageTitle} />

            <h1 className="sr-only">{pageTitle}</h1>

            <div className="flex flex-col space-y-10">
                <AttentionList items={attentionItems} />

                <div className="space-y-6 border-t pt-8 first:border-0 first:pt-0">
                    {permissions.canUpdateOrganization ? (
                        <>
                            <Heading
                                variant="small"
                                title="Organization settings"
                                description="Update your organization name and settings"
                            />

                            <Form
                                {...update.form(organization.slug)}
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
                                {({
                                    errors,
                                    processing,
                                    isDirty,
                                    recentlySuccessful,
                                }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Organization name
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                data-test="organization-name-input"
                                                defaultValue={organization.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="timezone">
                                                Reporting timezone
                                            </Label>
                                            <select
                                                id="timezone"
                                                name="timezone"
                                                data-test="organization-timezone-input"
                                                defaultValue={
                                                    organization.timezone ??
                                                    'UTC'
                                                }
                                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                            >
                                                {timezones.map((timezone) => (
                                                    <option
                                                        key={timezone.value}
                                                        value={timezone.value}
                                                    >
                                                        {timezone.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <p className="text-muted-foreground text-xs">
                                                Decides where a reporting day,
                                                month or quarter begins and
                                                ends. Orders themselves are
                                                always stored in UTC.
                                            </p>
                                            <InputError
                                                message={errors.timezone}
                                            />
                                        </div>

                                        <SaveButton
                                            processing={processing}
                                            isDirty={isDirty}
                                            recentlySuccessful={
                                                recentlySuccessful
                                            }
                                            data-test="organization-save-button"
                                        />
                                    </>
                                )}
                            </Form>
                        </>
                    ) : (
                        <>
                            <Heading
                                variant="small"
                                title={organization.name}
                                description="Only an owner or admin can change these settings."
                            />
                            <dl
                                className="grid gap-4 text-sm sm:grid-cols-2"
                                data-test="organization-details"
                            >
                                <div>
                                    <dt className="text-muted-foreground">
                                        Reporting timezone
                                    </dt>
                                    <dd className="font-medium">
                                        {organization.timezone ?? 'UTC'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Your role
                                    </dt>
                                    <dd className="font-medium">
                                        {currentMember?.role_label ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </>
                    )}
                </div>

                <div className="space-y-6 border-t pt-8 first:border-0 first:pt-0">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Heading
                            variant="small"
                            title="Organization members"
                            description={
                                permissions.canCreateInvitation
                                    ? 'Manage who belongs to this organization'
                                    : 'Who belongs to this organization'
                            }
                        />

                        {permissions.canCreateInvitation ? (
                            <Button
                                variant="outline"
                                data-test="invite-member-button"
                                onClick={() => setInviteDialogOpen(true)}
                            >
                                <UserPlus /> Invite member
                            </Button>
                        ) : null}
                    </div>

                    <ul className="divide-y overflow-hidden rounded-lg border">
                        {members.map((member) => (
                            <li
                                key={member.id}
                                data-test="member-row"
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="flex min-w-0 items-center gap-4">
                                    <Avatar className="h-10 w-10">
                                        {member.avatar ? (
                                            <AvatarImage
                                                src={member.avatar}
                                                alt={member.name}
                                            />
                                        ) : null}
                                        <AvatarFallback>
                                            {getInitials(member.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2 font-medium">
                                            <span className="truncate">
                                                {member.name}
                                            </span>
                                            {member.id === auth.user.id ? (
                                                <Badge
                                                    variant="outline"
                                                    data-test="member-you"
                                                >
                                                    You
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <div className="text-muted-foreground truncate text-sm">
                                            {member.email}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    {member.role !== 'owner' &&
                                    permissions.canUpdateMember ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    data-test="member-role-trigger"
                                                >
                                                    {member.role_label}
                                                    <ChevronDown className="ml-2 h-4 w-4 opacity-50" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuRadioGroup
                                                    value={member.role}
                                                    onValueChange={(value) =>
                                                        confirmRoleChange(
                                                            member,
                                                            value,
                                                        )
                                                    }
                                                >
                                                    {availableRoles.map(
                                                        (role) => (
                                                            <DropdownMenuRadioItem
                                                                key={role.value}
                                                                value={
                                                                    role.value
                                                                }
                                                                data-test="member-role-option"
                                                            >
                                                                {role.label}
                                                            </DropdownMenuRadioItem>
                                                        ),
                                                    )}
                                                </DropdownMenuRadioGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : (
                                        <Badge variant="secondary">
                                            {member.role_label}
                                        </Badge>
                                    )}

                                    {member.role !== 'owner' &&
                                    permissions.canRemoveMember ? (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Remove ${member.name}`}
                                                        data-test="member-remove-button"
                                                        onClick={() =>
                                                            confirmRemoveMember(
                                                                member,
                                                            )
                                                        }
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Remove member</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    ) : null}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                {invitations.length > 0 ? (
                    <div
                        id="invitations"
                        className="scroll-mt-6 space-y-6 border-t pt-8 first:border-0 first:pt-0"
                    >
                        <Heading
                            variant="small"
                            title="Pending invitations"
                            description="Invitations that haven't been accepted yet"
                        />

                        <ul className="divide-y overflow-hidden rounded-lg border">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.code}
                                    data-test="invitation-row"
                                    className="flex flex-wrap items-center justify-between gap-3 p-4"
                                >
                                    <div className="flex min-w-0 items-center gap-4">
                                        <div className="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                            <Mail className="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div className="min-w-0">
                                            <div className="truncate font-medium">
                                                {invitation.email}
                                            </div>
                                            <div className="text-muted-foreground text-sm">
                                                {invitation.role_label} · Sent{' '}
                                                {invitation.sent_at_diff}
                                                {invitation.expires_at_diff ? (
                                                    <span
                                                        data-test={
                                                            invitation.is_expired
                                                                ? 'invitation-expired'
                                                                : undefined
                                                        }
                                                        className={cn(
                                                            invitation.is_expired &&
                                                                'font-medium text-amber-700 dark:text-amber-400',
                                                        )}
                                                    >
                                                        {' · '}
                                                        {invitation.is_expired
                                                            ? `Expired ${invitation.expires_at_diff} ago`
                                                            : `Expires in ${invitation.expires_at_diff}`}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {permissions.canCreateInvitation ? (
                                            <Button
                                                variant={
                                                    invitation.is_expired
                                                        ? 'outline'
                                                        : 'ghost'
                                                }
                                                size="sm"
                                                data-test="invitation-resend-button"
                                                disabled={
                                                    resending ===
                                                    invitation.code
                                                }
                                                onClick={() =>
                                                    resend(invitation)
                                                }
                                            >
                                                <Send className="h-4 w-4" />
                                                Resend
                                            </Button>
                                        ) : null}

                                        {permissions.canCancelInvitation ? (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label={`Cancel invitation for ${invitation.email}`}
                                                            data-test="invitation-cancel-button"
                                                            onClick={() =>
                                                                confirmCancelInvitation(
                                                                    invitation,
                                                                )
                                                            }
                                                        >
                                                            <X className="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Cancel invitation</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                {permissions.canDeleteOrganization ? (
                    <div className="border-t pt-8 first:border-0 first:pt-0">
                        <DangerZone
                            title="Delete organization"
                            description="Permanently delete your organization"
                            warning="Everything in the organization goes with it. This cannot be undone."
                        >
                            <Button
                                variant="destructive"
                                data-test="delete-organization-button"
                                onClick={() => setDeleteDialogOpen(true)}
                            >
                                Delete organization
                            </Button>
                        </DangerZone>
                    </div>
                ) : null}
            </div>

            {permissions.canCreateInvitation ? (
                <InviteMemberModal
                    organization={organization}
                    availableRoles={availableRoles}
                    open={inviteDialogOpen}
                    onOpenChange={setInviteDialogOpen}
                />
            ) : null}

            <ChangeMemberRoleModal
                organization={organization}
                member={roleChange?.member ?? null}
                role={roleChange?.role ?? null}
                open={roleDialogOpen}
                onOpenChange={setRoleDialogOpen}
            />

            <RemoveMemberModal
                organization={organization}
                member={memberToRemove}
                open={removeMemberDialogOpen}
                onOpenChange={setRemoveMemberDialogOpen}
            />

            <CancelInvitationModal
                organization={organization}
                invitation={invitationToCancel}
                open={cancelInvitationDialogOpen}
                onOpenChange={setCancelInvitationDialogOpen}
            />

            {permissions.canDeleteOrganization ? (
                <DeleteOrganizationModal
                    organization={organization}
                    open={deleteDialogOpen}
                    onOpenChange={setDeleteDialogOpen}
                />
            ) : null}
        </>
    );
}

OrganizationEdit.layout = (props: {
    organization: { name: string; slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Organizations',
            href: index(),
        },
        {
            title: props.organization.name,
            href: edit(props.organization.slug),
        },
    ],
});
