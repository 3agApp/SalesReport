import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRightLeft,
    ChevronRight,
    LogOut,
    MoreHorizontal,
    Plus,
    Building2,
} from 'lucide-react';
import { useState } from 'react';
import CreateOrganizationModal from '@/components/create-organization-modal';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import LeaveOrganizationModal from '@/components/leave-organization-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { edit, index, switchMethod } from '@/routes/organizations';
import type { Organization } from '@/types';

type Props = {
    organizations: Organization[];
};

export default function OrganizationsIndex({ organizations }: Props) {
    const [leaveOrganizationDialogOpen, setLeaveOrganizationDialogOpen] =
        useState(false);
    const [organizationLeaving, setOrganizationLeaving] =
        useState<Organization | null>(null);

    const openLeaveOrganizationDialog = (organization: Organization) => {
        setOrganizationLeaving(organization);
        setLeaveOrganizationDialogOpen(true);
    };

    const switchTo = (organization: Organization) => {
        router.visit(switchMethod(organization.slug), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Organizations" />

            <h1 className="sr-only">Organizations</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Organizations"
                        description="Manage your organizations and organization memberships"
                    />

                    <CreateOrganizationModal>
                        <Button data-test="organizations-new-organization-button">
                            <Plus /> New organization
                        </Button>
                    </CreateOrganizationModal>
                </div>

                {organizations.length === 0 ? (
                    <div className="rounded-lg border">
                        <EmptyState
                            icon={Building2}
                            title="You don't belong to any organizations yet"
                            description="Create one, or ask an owner to invite you to theirs."
                        />
                    </div>
                ) : (
                    <ul className="divide-y overflow-hidden rounded-lg border">
                        {organizations.map((organization) => {
                            const canLeaveOrganization =
                                organization.role !== 'owner';
                            const hasMenu =
                                canLeaveOrganization || !organization.isCurrent;

                            return (
                                <li
                                    key={organization.id}
                                    data-test="organization-row"
                                    className="hover:bg-muted/50 flex items-center gap-2 pr-2 transition-colors"
                                >
                                    <Link
                                        href={edit(organization.slug)}
                                        data-test={
                                            organization.role === 'member'
                                                ? 'organization-view-button'
                                                : 'organization-edit-button'
                                        }
                                        className="flex min-w-0 flex-1 items-center gap-4 p-4"
                                    >
                                        <div className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-full">
                                            <Building2 className="text-muted-foreground size-5" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium break-words">
                                                    {organization.name}
                                                </span>
                                                {organization.isCurrent ? (
                                                    <Badge
                                                        variant="outline"
                                                        data-test="organization-current"
                                                    >
                                                        Current
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <span className="text-muted-foreground text-sm">
                                                {organization.roleLabel}
                                            </span>
                                        </div>
                                        <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                                    </Link>

                                    {hasMenu ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    aria-label={`More actions for ${organization.name}`}
                                                    data-test="organization-actions"
                                                >
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {!organization.isCurrent ? (
                                                    <DropdownMenuItem
                                                        data-test="organization-switch-button"
                                                        onSelect={() =>
                                                            switchTo(
                                                                organization,
                                                            )
                                                        }
                                                    >
                                                        <ArrowRightLeft />
                                                        Switch to this
                                                        organization
                                                    </DropdownMenuItem>
                                                ) : null}
                                                {canLeaveOrganization ? (
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        data-test="organization-leave-button"
                                                        onSelect={() =>
                                                            openLeaveOrganizationDialog(
                                                                organization,
                                                            )
                                                        }
                                                    >
                                                        <LogOut />
                                                        Leave organization
                                                    </DropdownMenuItem>
                                                ) : null}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <LeaveOrganizationModal
                organization={organizationLeaving}
                open={leaveOrganizationDialogOpen}
                onOpenChange={setLeaveOrganizationDialogOpen}
            />
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Organizations',
            href: index(),
        },
    ],
};
