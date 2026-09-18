import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { onboarding } from '@/routes';
import { index as invitationsIndex } from '@/routes/invitations';
import { store } from '@/routes/organizations';

export default function Onboarding() {
    const { pendingInvitationsCount } = usePage().props;

    return (
        <>
            <Head title="Create your first organization" />

            <div className="workspace-page">
                <div className="mx-auto w-full max-w-xl space-y-6">
                    <div className="page-heading">
                        <h1 className="page-title">
                            Create your first organization
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            An organization is where your team keeps its shops
                            and sales figures together. You can add more
                            organizations and invite people later.
                        </p>
                    </div>

                    {pendingInvitationsCount > 0 ? (
                        <div
                            data-test="onboarding-invitations"
                            className="bg-card flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-5 shadow-xs"
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <div className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-full">
                                    <Mail className="text-muted-foreground size-5" />
                                </div>
                                <p className="text-sm">
                                    {pendingInvitationsCount === 1
                                        ? 'You have been invited to an organization.'
                                        : `You have been invited to ${pendingInvitationsCount} organizations.`}
                                </p>
                            </div>
                            <Button variant="secondary" asChild>
                                <Link href={invitationsIndex()}>
                                    View invitations
                                </Link>
                            </Button>
                        </div>
                    ) : null}

                    <Form
                        {...store.form()}
                        className="bg-card space-y-6 rounded-2xl border p-6 shadow-xs"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        Organization name
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        data-test="onboarding-organization-name"
                                        placeholder="Acme Group"
                                        autoFocus
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    data-test="onboarding-organization-submit"
                                    disabled={processing}
                                >
                                    Create organization
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

Onboarding.layout = {
    breadcrumbs: [
        {
            title: 'Get started',
            href: onboarding(),
        },
    ],
};
