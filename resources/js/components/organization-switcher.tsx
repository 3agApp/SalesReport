import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import CreateOrganizationModal from '@/components/create-organization-modal';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import { switchMethod } from '@/routes/organizations';
import type { Organization } from '@/types';

export function OrganizationSwitcher() {
    const page = usePage();
    const isMobile = useIsMobile();
    const currentOrganization = page.props.currentOrganization;
    const organizations = page.props.organizations ?? [];

    const switchOrganization = (organization: Organization) => {
        const previousOrganizationSlug = currentOrganization?.slug;

        router.visit(switchMethod(organization.slug), {
            onFinish: () => {
                if (
                    !previousOrganizationSlug ||
                    typeof window === 'undefined'
                ) {
                    router.reload();

                    return;
                }

                const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
                const segment = `/${previousOrganizationSlug}`;

                if (currentUrl.includes(segment)) {
                    router.visit(
                        currentUrl.replace(segment, `/${organization.slug}`),
                        {
                            replace: true,
                        },
                    );

                    return;
                }

                router.reload();
            },
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    data-test="organization-switcher-trigger"
                    className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground w-full justify-start px-2 has-[>svg]:px-2"
                >
                    <Building2 className="hidden size-4 shrink-0 group-data-[collapsible=icon]:block" />
                    <div className="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                        <span className="truncate font-semibold">
                            {currentOrganization?.name ?? 'Select organization'}
                        </span>
                    </div>
                    <ChevronsUpDown className="ml-auto group-data-[collapsible=icon]:hidden" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                side={isMobile ? 'bottom' : 'right'}
                align="start"
                sideOffset={4}
            >
                <DropdownMenuLabel className="text-muted-foreground text-xs">
                    Organizations
                </DropdownMenuLabel>
                {organizations.map((organization) => (
                    <DropdownMenuItem
                        key={organization.id}
                        data-test="organization-switcher-item"
                        className="cursor-pointer gap-2 p-2"
                        onSelect={() => switchOrganization(organization)}
                    >
                        {organization.name}
                        {currentOrganization?.id === organization.id && (
                            <Check className="ml-auto h-4 w-4" />
                        )}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <CreateOrganizationModal>
                    <DropdownMenuItem
                        data-test="organization-switcher-new-organization"
                        className="cursor-pointer gap-2 p-2"
                        onSelect={(event) => event.preventDefault()}
                    >
                        <Plus className="h-4 w-4" />
                        <span className="text-muted-foreground">
                            New organization
                        </span>
                    </DropdownMenuItem>
                </CreateOrganizationModal>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
