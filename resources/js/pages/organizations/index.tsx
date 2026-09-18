import { Head, Link } from '@inertiajs/react';
import { Eye, LogOut, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import CreateOrganizationModal from '@/components/create-organization-modal';
import Heading from '@/components/heading';
import LeaveOrganizationModal from '@/components/leave-organization-modal';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index } from '@/routes/organizations';
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

                <div className="space-y-3">
                    {organizations.map((organization) => {
                        const canLeaveOrganization =
                            organization.role !== 'owner';

                        return (
                            <div
                                key={organization.id}
                                data-test="organization-row"
                                className="bg-muted/20 flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium break-words">
                                                {organization.name}
                                            </span>
                                        </div>
                                        <span className="text-muted-foreground text-sm">
                                            {organization.roleLabel}
                                        </span>
                                    </div>
                                </div>

                                <TooltipProvider>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {canLeaveOrganization ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="organization-leave-button"
                                                        onClick={() =>
                                                            openLeaveOrganizationDialog(
                                                                organization,
                                                            )
                                                        }
                                                    >
                                                        <LogOut className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Leave organization</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : null}

                                        {organization.role === 'member' ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="organization-view-button"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(
                                                                organization.slug,
                                                            )}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>View organization</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="organization-edit-button"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(
                                                                organization.slug,
                                                            )}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Edit organization</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                </TooltipProvider>
                            </div>
                        );
                    })}

                    {organizations.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center">
                            You don&apos;t belong to any organizations yet.
                        </p>
                    ) : null}
                </div>
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
