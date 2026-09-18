import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/shops';
import type { Organization, Shop, ShopPlatformOption } from '@/types';

type Props = {
    organization: Organization;
    shop: Shop | null;
    platforms: ShopPlatformOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ShopFormModal({
    organization,
    shop,
    platforms,
    open,
    onOpenChange,
}: Props) {
    const formRoute = shop
        ? update.form([organization.slug, shop.id])
        : store.form(organization.slug);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <Form
                    key={`${shop?.id ?? 'new'}-${String(open)}`}
                    {...formRoute}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {shop ? 'Edit shop' : 'Add shop'}
                                </DialogTitle>
                                <DialogDescription>
                                    {shop
                                        ? `Update the connection details for ${shop.name}.`
                                        : `Connect a WooCommerce shop to ${organization.name}.`}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="shop-name">Name</Label>
                                    <Input
                                        id="shop-name"
                                        name="name"
                                        data-test="shop-name"
                                        defaultValue={shop?.name}
                                        placeholder="Toys Online"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="shop-url">
                                            Store URL
                                        </Label>
                                        <Input
                                            id="shop-url"
                                            name="url"
                                            type="url"
                                            inputMode="url"
                                            data-test="shop-url"
                                            defaultValue={shop?.url}
                                            placeholder="https://example.ch"
                                            autoComplete="off"
                                            spellCheck={false}
                                            required
                                        />
                                        <InputError message={errors.url} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="shop-platform">
                                            Platform
                                        </Label>
                                        <Select
                                            name="platform"
                                            defaultValue={
                                                shop?.platform ?? 'woocommerce'
                                            }
                                        >
                                            <SelectTrigger
                                                id="shop-platform"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select a platform" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {platforms.map((platform) => (
                                                    <SelectItem
                                                        key={platform.value}
                                                        value={platform.value}
                                                    >
                                                        {platform.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.platform} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="shop-consumer-key">
                                        Consumer key{' '}
                                        {shop && (
                                            <span className="text-muted-foreground font-normal">
                                                (leave blank to keep{' '}
                                                {shop.consumerKeyHint})
                                            </span>
                                        )}
                                    </Label>
                                    <Input
                                        id="shop-consumer-key"
                                        name="consumer_key"
                                        data-test="shop-consumer-key"
                                        placeholder="ck_..."
                                        autoComplete="off"
                                        spellCheck={false}
                                        required={!shop}
                                    />
                                    <InputError message={errors.consumer_key} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="shop-consumer-secret">
                                        Consumer secret{' '}
                                        {shop && (
                                            <span className="text-muted-foreground font-normal">
                                                (leave blank to keep the current
                                                one)
                                            </span>
                                        )}
                                    </Label>
                                    <Input
                                        id="shop-consumer-secret"
                                        name="consumer_secret"
                                        type="password"
                                        data-test="shop-consumer-secret"
                                        placeholder="cs_..."
                                        autoComplete="new-password"
                                        spellCheck={false}
                                        required={!shop}
                                    />
                                    <InputError
                                        message={errors.consumer_secret}
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        Create a read-only REST API key under
                                        WooCommerce → Settings → Advanced → REST
                                        API. Keys are encrypted and never shown
                                        again.
                                    </p>
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="shop-submit"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {shop ? 'Save changes' : 'Add shop'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
