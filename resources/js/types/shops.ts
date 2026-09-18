export type ShopPlatform = 'woocommerce';

export type Shop = {
    id: number;
    name: string;
    url: string;
    host: string;
    platform: ShopPlatform;
    platformLabel: string;
    consumerKeyHint: string;
    updatedAtDiff: string | null;
};

export type ShopFilters = {
    search: string;
};

export type ShopPlatformOption = {
    value: ShopPlatform;
    label: string;
};

export type ShopStats = {
    total: number;
};
