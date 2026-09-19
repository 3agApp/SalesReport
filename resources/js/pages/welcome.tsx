import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    LineChart,
    Plug,
    ShoppingCart,
    Store,
    TrendingUp,
    Users,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home, login } from '@/routes';

const features = [
    {
        icon: Store,
        title: 'Every shop, one workspace',
        description:
            'Group your storefronts under an organization and switch between them in a click.',
    },
    {
        icon: Plug,
        title: 'Connects to WooCommerce',
        description:
            'Paste a read-only REST API key and the shop starts reporting. Keys are stored encrypted.',
    },
    {
        icon: BarChart3,
        title: 'Sales side by side',
        description:
            'Compare revenue and orders across shops instead of logging into each one.',
    },
    {
        icon: Users,
        title: 'Built for your team',
        description:
            'Invite colleagues as owners, admins or members and keep access in check.',
    },
];

const previewShops = [
    {
        name: 'Toys Online',
        host: 'toysonline.ch',
        revenue: '$18,420',
        orders: 312,
        trend: '+12.4%',
    },
    {
        name: 'Tigerbox',
        host: 'tigerbox.ch',
        revenue: '$11,905',
        orders: 204,
        trend: '+8.1%',
    },
    {
        name: 'Natural Aqua',
        host: 'naturalaqua.ch',
        revenue: '$7,240',
        orders: 141,
        trend: '+3.7%',
    },
    {
        name: 'Living Nature',
        host: 'livingnature.ch',
        revenue: '$4,880',
        orders: 96,
        trend: '−1.2%',
    },
];

export default function Welcome() {
    const { auth, currentOrganization, name } = usePage().props;
    const dashboardUrl = currentOrganization
        ? dashboard(currentOrganization.slug)
        : '/';

    return (
        <>
            <Head title="Sales reporting for every WooCommerce shop you run" />
            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-semibold tracking-tight"
                    >
                        <div className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-5" />
                        </div>
                        {name}
                    </Link>

                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboardUrl}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={login()}>Get started</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="flex-1">
                    <section className="mx-auto max-w-6xl px-6 pt-12 pb-16 text-center lg:pt-20">
                        <div className="bg-muted/60 text-muted-foreground mb-6 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium">
                            <TrendingUp className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            Sales reporting for multi-shop WooCommerce sellers
                        </div>
                        <h1 className="mx-auto max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                            Every shop&apos;s sales, on one report.
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-6 max-w-2xl text-base text-balance sm:text-lg">
                            {name} pulls the numbers from all of your
                            WooCommerce stores into a single place, so you stop
                            stitching together dashboards by hand.
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <Button size="lg" asChild>
                                <Link
                                    href={auth.user ? dashboardUrl : login()}
                                >
                                    {auth.user
                                        ? 'Open dashboard'
                                        : 'Start for free'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                            {auth.user ? null : (
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                            )}
                        </div>
                    </section>

                    <section className="mx-auto max-w-5xl px-6">
                        <DashboardPreview />
                    </section>

                    <section className="mx-auto grid max-w-6xl gap-4 px-6 py-20 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature) => (
                            <div
                                key={feature.title}
                                className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-6"
                            >
                                <div className="bg-muted mb-4 flex size-10 items-center justify-center rounded-lg">
                                    <feature.icon className="size-5" />
                                </div>
                                <h2 className="font-medium">{feature.title}</h2>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="border-t">
                    <div className="text-muted-foreground mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-6 py-6 text-sm sm:flex-row">
                        <span className="text-foreground flex items-center gap-2 font-medium">
                            <AppLogoIcon className="size-4" />
                            {name}
                        </span>
                        <span>
                            © {new Date().getFullYear()} {name}. All rights
                            reserved.
                        </span>
                    </div>
                </footer>
            </div>
        </>
    );
}

function DashboardPreview() {
    return (
        <div
            aria-hidden="true"
            className="bg-card overflow-hidden rounded-xl border shadow-xl shadow-neutral-900/5 dark:shadow-black/40"
        >
            <div className="bg-muted/40 flex items-center gap-2 border-b px-4 py-3">
                <span className="size-2.5 rounded-full bg-red-400/80" />
                <span className="size-2.5 rounded-full bg-amber-400/80" />
                <span className="size-2.5 rounded-full bg-emerald-400/80" />
                <span className="text-muted-foreground ml-3 text-xs">
                    Acme Group · Sales
                </span>
            </div>

            <div className="grid gap-3 border-b p-4 sm:grid-cols-3">
                {[
                    {
                        label: 'Revenue (30 days)',
                        value: '$42,445',
                        icon: LineChart,
                    },
                    { label: 'Orders', value: '753', icon: ShoppingCart },
                    { label: 'Connected shops', value: '6', icon: Store },
                ].map((stat) => (
                    <div
                        key={stat.label}
                        className="rounded-lg border p-3 text-left"
                    >
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            {stat.label}
                            <stat.icon className="size-3.5" />
                        </div>
                        <div className="mt-1 text-lg font-semibold tabular-nums">
                            {stat.value}
                        </div>
                    </div>
                ))}
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="text-muted-foreground border-b">
                        <tr>
                            <th className="px-4 py-2.5 font-medium">Shop</th>
                            <th className="px-4 py-2.5 text-right font-medium">
                                Orders
                            </th>
                            <th className="px-4 py-2.5 text-right font-medium">
                                Revenue
                            </th>
                            <th className="px-4 py-2.5 text-right font-medium">
                                Trend
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {previewShops.map((shop) => (
                            <tr
                                key={shop.host}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3 whitespace-nowrap">
                                    <div className="font-medium">
                                        {shop.name}
                                    </div>
                                    <div className="text-muted-foreground text-xs">
                                        {shop.host}
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {shop.orders}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {shop.revenue}
                                </td>
                                <td
                                    className={
                                        shop.trend.startsWith('+')
                                            ? 'px-4 py-3 text-right text-emerald-600 tabular-nums dark:text-emerald-400'
                                            : 'px-4 py-3 text-right text-red-600 tabular-nums dark:text-red-400'
                                    }
                                >
                                    {shop.trend}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
