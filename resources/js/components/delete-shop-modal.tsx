import { router } from '@inertiajs/react';
import { useState } from 'react';
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
import { destroy } from '@/routes/shops';
import type { Organization, Shop } from '@/types';

type Props = {
    organization: Organization;
    shop: Shop | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteShopModal({
    organization,
    shop,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const deleteShop = () => {
        if (!shop) {
            return;
        }

        router.visit(destroy([organization.slug, shop.id]), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove shop</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove{' '}
                        <strong>{shop?.name}</strong> from {organization.name}?
                        Its stored API credentials will be deleted. This action
                        cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="delete-shop-confirm"
                        disabled={processing}
                        onClick={deleteShop}
                    >
                        Remove shop
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
